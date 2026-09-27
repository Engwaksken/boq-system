<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Readiness check for uptime monitors: database, cache, storage and queue backlog.
 * Returns 200 when healthy and 503 when any critical check fails. No secrets are exposed.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'cache' => $this->check(function () {
                Cache::put('health-check', 'ok', 10);

                return Cache::get('health-check') === 'ok' ?: throw new \RuntimeException('cache read mismatch');
            }),
            'storage' => $this->check(function () {
                Storage::disk('local')->put('health-check.txt', now()->toIso8601String());
                Storage::disk('local')->delete('health-check.txt');
            }),
        ];

        $queue = ['status' => 'ok'];

        try {
            if (Schema::hasTable('failed_jobs')) {
                $queue['failed_last_24h'] = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
            }

            if (config('queue.default') === 'database' && Schema::hasTable('jobs')) {
                $queue['pending'] = DB::table('jobs')->count();
                $oldest = DB::table('jobs')->min('created_at');
                // A job waiting over 15 minutes usually means no worker is running.
                if ($oldest && now()->timestamp - (int) $oldest > 900) {
                    $queue['status'] = 'degraded';
                    $queue['message'] = 'Queued jobs are waiting; check the queue worker.';
                }
            }
        } catch (Throwable) {
            $queue = ['status' => 'unknown'];
        }

        $checks['queue'] = $queue;

        $healthy = collect(['database', 'cache', 'storage'])->every(fn ($key) => $checks[$key]['status'] === 'ok');

        return response()->json([
            'status' => $healthy ? 'ok' : 'failing',
            'time' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    /** @return array{status: string, message?: string} */
    private function check(callable $probe): array
    {
        try {
            $probe();

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            report($e);

            return ['status' => 'failing', 'message' => class_basename($e)];
        }
    }
}
