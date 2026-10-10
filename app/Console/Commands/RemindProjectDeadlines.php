<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\UserNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemindProjectDeadlines extends Command
{
    protected $signature = 'projects:remind {--days=30,14,7,3,1 : Comma-separated days before the expected completion date to remind}';

    protected $description = 'Send in-app reminders for projects approaching or past their expected completion date';

    public function handle(): int
    {
        $sent = 0;
        $days = collect(explode(',', (string) $this->option('days')))
            ->map(fn ($day) => (int) trim($day))
            ->filter(fn ($day) => $day > 0)
            ->unique();

        foreach ($days as $day) {
            Project::query()
                ->whereIn('status', ['draft', 'active'])
                ->whereNotNull('expected_completion_date')
                ->whereDate('expected_completion_date', now()->addDays($day)->toDateString())
                ->chunkById(100, function ($projects) use ($day, &$sent) {
                    foreach ($projects as $project) {
                        $sent += $this->notify($project, $day);
                    }
                });
        }

        // Overdue: the expected completion date is in the past and the project is still open.
        Project::query()
            ->whereIn('status', ['draft', 'active'])
            ->whereNotNull('expected_completion_date')
            ->whereDate('expected_completion_date', '<', now()->toDateString())
            ->chunkById(100, function ($projects) use (&$sent) {
                foreach ($projects as $project) {
                    $sent += $this->notify($project, 'overdue');
                }
            });

        $this->info("Sent {$sent} project deadline reminder(s).");

        return self::SUCCESS;
    }

    private function notify(Project $project, int|string $day): int
    {
        $userIds = collect([$project->user_id])
            ->merge($project->assignments()->pluck('user_id'))
            ->filter()
            ->unique();

        $sent = 0;

        foreach ($userIds as $userId) {
            $key = "project-deadline-{$project->id}-{$day}";

            $created = DB::transaction(function () use ($userId, $project, $day, $key): bool {
                // A per-user row lock serializes duplicate scheduler runs.
                \App\Models\User::query()->whereKey($userId)->lockForUpdate()->first();

                if (UserNotification::where('user_id', $userId)->where('data->reminder_key', $key)->exists()) {
                    return false;
                }

                if ($day === 'overdue') {
                    $title = 'Project deadline passed';
                    $message = sprintf(
                        '"%s" was due on %s. Update its status or completion date so it is not lost.',
                        $project->name,
                        $project->expected_completion_date->toDateString()
                    );
                } else {
                    $title = $day === 1 ? 'Project due tomorrow' : "Project due in {$day} days";
                    $message = sprintf(
                        '"%s" is due on %s.',
                        $project->name,
                        $project->expected_completion_date->toDateString()
                    );
                }

                UserNotification::create([
                    'user_id' => $userId,
                    'type' => 'project',
                    'title' => $title,
                    'message' => $message,
                    'data' => ['project_id' => $project->id, 'reminder_key' => $key],
                ]);

                return true;
            });

            if ($created) {
                $sent++;
            }
        }

        return $sent;
    }
}
