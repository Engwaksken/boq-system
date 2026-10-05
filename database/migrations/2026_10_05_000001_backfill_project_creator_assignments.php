<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve explicit revocations: any historical creator assignment,
        // including a soft-deleted one, prevents an automatic grant.
        DB::table('projects')->join('users', 'users.id', '=', 'projects.user_id')
            ->whereNull('projects.deleted_at')
            ->whereNotNull('projects.organisation_id')
            ->whereColumn('projects.organisation_id', 'users.organisation_id')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('project_assignments')
                ->whereColumn('project_assignments.project_id', 'projects.id')
                ->whereColumn('project_assignments.user_id', 'projects.user_id'))
            ->select(['projects.id', 'projects.user_id'])
            ->chunkById(500, function ($projects) {
                $now = now();
                DB::table('project_assignments')->insert($projects->map(fn ($project) => [
                    'project_id' => $project->id, 'user_id' => $project->user_id,
                    'role' => 'project-manager', 'assigned_by' => $project->user_id,
                    'created_at' => $now, 'updated_at' => $now,
                ])->all());
            }, 'projects.id', 'id');
    }

    public function down(): void
    {
        // Data backfills are retained on rollback; deleting these grants could
        // destroy assignments subsequently edited by an administrator. Rolling
        // back the assignment-table migration removes the table normally.
    }
};
