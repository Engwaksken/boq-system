<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\AiProviderUsage;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Usage, credit and expiry of the AI providers, so admins can top up before a
 * provider runs out, and are told when one has.
 */
class AiUsageMonitor
{
    /** Warn when less than this share of the monthly token limit is left. */
    public const WARN_AT_USED_SHARE = 0.9;

    /** Warn this many days before the credit expires. */
    public const WARN_DAYS_BEFORE_EXPIRY = 7;

    /** Hours between repeated alerts for the same provider. */
    public const ALERT_EVERY_HOURS = 6;

    /**
     * This month's usage per provider id.
     *
     * @param  Collection<int, AiProvider>  $providers
     * @return array<int, array{requests: int, failed: int, input: int, output: int, tokens: int, last_used_at: ?string, last_error: ?string}>
     */
    public function monthlyUsage(Collection $providers): array
    {
        $ids = $providers->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        $rows = AiProviderUsage::query()
            ->whereIn('ai_provider_id', $ids)
            ->where('created_at', '>=', now()->startOfMonth())
            ->groupBy('ai_provider_id')
            ->selectRaw('ai_provider_id, COUNT(*) AS requests')
            ->selectRaw('SUM(CASE WHEN successful = 0 THEN 1 ELSE 0 END) AS failed')
            ->selectRaw('COALESCE(SUM(input_units), 0) AS input_units_total, COALESCE(SUM(output_units), 0) AS output_units_total')
            ->selectRaw('MAX(created_at) AS last_used_at')
            ->get()
            ->keyBy('ai_provider_id');

        $lastErrors = AiProviderUsage::query()
            ->whereIn('ai_provider_id', $ids)
            ->where('successful', false)
            ->where('created_at', '>=', now()->subDays(7))
            ->latest('id')
            ->get(['ai_provider_id', 'error_category'])
            ->unique('ai_provider_id')
            ->pluck('error_category', 'ai_provider_id');

        $usage = [];
        foreach ($ids as $id) {
            $row = $rows->get($id);
            $input = (int) ($row->input_units_total ?? 0);
            $output = (int) ($row->output_units_total ?? 0);
            $usage[$id] = [
                'requests' => (int) ($row->requests ?? 0),
                'failed' => (int) ($row->failed ?? 0),
                'input' => $input,
                'output' => $output,
                'tokens' => $input + $output,
                'last_used_at' => $row->last_used_at ?? null,
                'last_error' => $lastErrors->get($id),
            ];
        }

        return $usage;
    }

    /**
     * ok | warning | exhausted, with the reasons shown to admins.
     *
     * @param  array{tokens: int}|null  $usage
     * @return array{state: string, reasons: list<string>, used_share: ?float, days_left: ?int}
     */
    public function status(AiProvider $provider, ?array $usage = null): array
    {
        $usage ??= $this->monthlyUsage(collect([$provider]))[$provider->id];
        $reasons = [];
        $state = 'ok';
        $raise = function (string $to) use (&$state): void {
            if ($to === 'exhausted' || $state === 'ok') {
                $state = $to;
            }
        };

        $usedShare = $provider->monthly_token_limit ? $usage['tokens'] / max(1, (int) $provider->monthly_token_limit) : null;
        if ($usedShare !== null && $usedShare >= 1) {
            $raise('exhausted');
            $reasons[] = __('Monthly token limit reached.');
        } elseif ($usedShare !== null && $usedShare >= self::WARN_AT_USED_SHARE) {
            $raise('warning');
            $reasons[] = __(':percent% of the monthly token limit used.', ['percent' => (int) round($usedShare * 100)]);
        }

        $daysLeft = $provider->credit_expires_at ? (int) now()->startOfDay()->diffInDays($provider->credit_expires_at, false) : null;
        if ($daysLeft !== null && $daysLeft < 0) {
            $raise('exhausted');
            $reasons[] = __('Credit expired on :date.', ['date' => $provider->credit_expires_at->format('j M Y')]);
        } elseif ($daysLeft !== null && $daysLeft <= self::WARN_DAYS_BEFORE_EXPIRY) {
            $raise('warning');
            $reasons[] = trans_choice('Credit expires in :count day.|Credit expires in :count days.', max($daysLeft, 0), ['count' => max($daysLeft, 0)]);
        }

        if ($provider->credit_balance !== null) {
            if ((float) $provider->credit_balance <= 0) {
                $raise('exhausted');
                $reasons[] = __('No credit left.');
            } elseif ($provider->low_credit_threshold !== null && (float) $provider->credit_balance <= (float) $provider->low_credit_threshold) {
                $raise('warning');
                $reasons[] = __('Credit is low.');
            }
        }

        if ($provider->credit_exhausted_at !== null) {
            $raise('exhausted');
            $reasons[] = __('The provider reported no credit or quota on :date.', ['date' => $provider->credit_exhausted_at->format('j M Y H:i')]);
        }

        return ['state' => $state, 'reasons' => $reasons, 'used_share' => $usedShare, 'days_left' => $daysLeft];
    }

    /** True when the provider should not be asked (known to have nothing left). */
    public function isExhausted(AiProvider $provider): bool
    {
        // Cheap checks first; usage is only counted when a limit is set.
        // A "no credit" answer is re-checked after an hour, in case it was topped up.
        if (($provider->credit_exhausted_at !== null && $provider->credit_exhausted_at->gt(now()->subHour()))
            || ($provider->credit_balance !== null && (float) $provider->credit_balance <= 0)
            || ($provider->credit_expires_at !== null && $provider->credit_expires_at->endOfDay()->isPast())) {
            return true;
        }

        if ($provider->monthly_token_limit) {
            $tokens = $this->monthlyUsage(collect([$provider]))[$provider->id]['tokens'];

            return $tokens >= (int) $provider->monthly_token_limit;
        }

        return false;
    }

    /** The provider rejected a request for lack of credit or quota. */
    public function markExhausted(AiProvider $provider, string $detail = ''): void
    {
        if ($provider->credit_exhausted_at === null) {
            $provider->forceFill(['credit_exhausted_at' => now()])->saveQuietly();
        }

        $this->alertAdmins(
            $provider,
            __('Provider has no tokens or credit left'),
            __('The provider refused a request because its credit or quota is used up:detail. BOQ pricing and price scans fall back to other enabled providers; top up the account, then clear the warning under AI API Settings.', [
                'detail' => $detail !== '' ? ' ('.$detail.')' : '',
            ]),
        );
    }

    /** A successful answer proves the provider has credit again. */
    public function markWorking(AiProvider $provider): void
    {
        if ($provider->credit_exhausted_at !== null) {
            $provider->forceFill(['credit_exhausted_at' => null])->saveQuietly();
        }
    }

    /** Warn admins when a provider is close to running out (throttled). */
    public function warnIfLow(AiProvider $provider): void
    {
        $status = $this->status($provider);
        if ($status['state'] === 'ok') {
            return;
        }

        $this->alertAdmins(
            $provider,
            $status['state'] === 'exhausted'
                ? __('Provider has no tokens or credit left')
                : __('Provider is running low'),
            implode(' ', $status['reasons']).' '.__('Top up the account before it runs out.'),
        );
    }

    /**
     * Reads the live balance from providers that report it (DeepSeek, OpenRouter).
     *
     * @return bool whether a balance was read
     */
    public function refreshBalance(AiProvider $provider): bool
    {
        $base = strtolower((string) $provider->api_base_url).' '.strtolower((string) $provider->provider_type).' '.strtolower((string) $provider->key);

        try {
            if (str_contains($base, 'deepseek') && filled($provider->api_key)) {
                $root = preg_replace('#/v1/?$#', '', rtrim((string) ($provider->api_base_url ?: 'https://api.deepseek.com'), '/'));
                $response = Http::timeout(15)->withToken($provider->api_key)->acceptJson()->get($root.'/user/balance');
                if (! $response->successful()) {
                    return false;
                }
                $info = collect($response->json('balance_infos', []))->first();
                if (! $info) {
                    return false;
                }
                $this->storeBalance($provider, (float) ($info['total_balance'] ?? 0), (string) ($info['currency'] ?? 'USD'), $response->json('is_available') === false);

                return true;
            }

            if (str_contains($base, 'openrouter') && filled($provider->api_key)) {
                $response = Http::timeout(15)->withToken($provider->api_key)->acceptJson()->get('https://openrouter.ai/api/v1/credits');
                if (! $response->successful()) {
                    return false;
                }
                $left = (float) $response->json('data.total_credits', 0) - (float) $response->json('data.total_usage', 0);
                $this->storeBalance($provider, $left, 'USD', $left <= 0);

                return true;
            }
        } catch (Throwable $exception) {
            Log::info('AI provider balance could not be read.', ['provider' => $provider->key, 'error' => $exception->getMessage()]);
        }

        return false;
    }

    private function storeBalance(AiProvider $provider, float $balance, string $currency, bool $unavailable): void
    {
        $provider->forceFill([
            'credit_balance' => $balance,
            'credit_currency' => strtoupper($currency),
            'balance_checked_at' => now(),
            'credit_exhausted_at' => $unavailable || $balance <= 0 ? ($provider->credit_exhausted_at ?? now()) : null,
        ])->saveQuietly();
    }

    /** In-app notification to super admins and admins, at most every few hours per provider. */
    public function alertAdmins(AiProvider $provider, string $title, string $message): void
    {
        if ($provider->last_credit_alert_at !== null
            && $provider->last_credit_alert_at->gt(now()->subHours(self::ALERT_EVERY_HOURS))) {
            return;
        }

        $provider->forceFill(['last_credit_alert_at' => now()])->saveQuietly();

        $admins = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['super-admin', 'super_admin', 'admin']))
            ->when($provider->organisation_id, fn ($q) => $q->where(function ($inner) use ($provider) {
                $inner->where('organisation_id', $provider->organisation_id)
                    ->orWhereHas('roles', fn ($r) => $r->whereIn('slug', ['super-admin', 'super_admin']));
            }))
            ->get(['id']);

        foreach ($admins as $admin) {
            try {
                UserNotification::create([
                    'user_id' => $admin->id,
                    'type' => 'ai_provider_credit',
                    'title' => $title,
                    'message' => $message,
                    'data' => ['ai_provider_id' => $provider->id, 'url' => route('admin.ai-providers')],
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        Log::warning('AI provider credit alert.', ['provider' => $provider->key, 'title' => $title]);
    }
}
