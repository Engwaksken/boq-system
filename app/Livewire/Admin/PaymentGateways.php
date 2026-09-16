<?php

namespace App\Livewire\Admin;

use App\Models\PaymentGateway;
use Illuminate\Validation\Rule;
use JsonException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PaymentGateways extends Component
{
    use WithPagination;

    private const SECRET_MASK = '***stored***';

    public bool $showForm = false;
    public ?int $editingId = null;
    public string $configJson = '{}';
    public string $supportedCurrenciesCsv = 'UGX, USD';
    public string $supportedCountriesCsv = 'UG';
    public string $supportedMethodsCsv = '';

    public array $form = [
        'name' => '',
        'code' => '',
        'driver' => 'mtn_momo',
        'description' => '',
        'is_active' => true,
        'is_test_mode' => true,
        'webhook_url' => '',
        'payment_timeout_seconds' => 900,
        'refund_settings' => ['allow_refunds' => true, 'refund_window_days' => 30],
    ];

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $gateway = PaymentGateway::findOrFail($id);
        $this->editingId = $gateway->id;
        $this->form = [
            'name' => $gateway->name,
            'code' => $gateway->code,
            'driver' => $gateway->driver,
            'description' => $gateway->description,
            'is_active' => $gateway->is_active,
            'is_test_mode' => $gateway->is_test_mode,
            'webhook_url' => $gateway->webhook_url,
            'payment_timeout_seconds' => $gateway->payment_timeout_seconds,
            'refund_settings' => $gateway->refund_settings ?? ['allow_refunds' => true, 'refund_window_days' => 30],
        ];
        $this->configJson = json_encode($this->maskedConfig($gateway->config ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $this->supportedCurrenciesCsv = implode(', ', $gateway->supported_currencies ?? []);
        $this->supportedCountriesCsv = implode(', ', $gateway->supported_countries ?? []);
        $this->supportedMethodsCsv = implode(', ', $gateway->supported_methods ?? []);
        $this->showForm = true;
    }

    public function save(): void
    {
        $drivers = array_keys($this->driverOptions());
        $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:50', Rule::unique('payment_gateways', 'code')->ignore($this->editingId)],
            'form.driver' => ['required', Rule::in($drivers)],
            'form.description' => ['nullable', 'string'],
            'form.is_active' => ['boolean'],
            'form.is_test_mode' => ['boolean'],
            'form.payment_timeout_seconds' => ['required', 'integer', 'min:30', 'max:86400'],
            'configJson' => ['required', 'json'],
            'supportedCurrenciesCsv' => ['nullable', 'string', 'max:255'],
            'supportedCountriesCsv' => ['nullable', 'string', 'max:255'],
            'supportedMethodsCsv' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $config = json_decode($this->configJson, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->addError('configJson', 'Configuration must be valid JSON.');
            return;
        }

        $gateway = $this->editingId ? PaymentGateway::findOrFail($this->editingId) : new PaymentGateway();
        $config = $this->restoreMaskedSecrets(is_array($config) ? $config : [], $gateway->config ?? []);

        $payload = array_merge($this->form, [
            'config' => $config,
            'supported_currencies' => $this->csv($this->supportedCurrenciesCsv, true),
            'supported_countries' => $this->csv($this->supportedCountriesCsv, true),
            'supported_methods' => $this->csv($this->supportedMethodsCsv, false),
        ]);

        $gateway->fill($payload)->save();
        session()->flash('message', $this->editingId ? 'Payment gateway updated successfully.' : 'Payment gateway created successfully.');
        $this->cancel();
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetForm();
        $this->resetValidation();
    }

    public function toggleActive(int $id): void
    {
        $gateway = PaymentGateway::findOrFail($id);
        $gateway->update(['is_active' => ! $gateway->is_active]);
        session()->flash('message', 'Payment gateway status updated.');
    }

    public function loadDriverTemplate(): void
    {
        $templates = [
            'mtn_momo' => ['base_url' => 'https://sandbox.momodeveloper.mtn.com', 'subscription_key' => '', 'api_user' => '', 'api_key' => '', 'target_environment' => 'sandbox', 'currency_override' => 'EUR'],
            'airtel_money' => ['base_url' => 'https://openapiuat.airtel.africa', 'client_id' => '', 'client_secret' => '', 'country' => 'UG', 'currency' => 'UGX'],
            'flutterwave' => ['base_url' => 'https://api.flutterwave.com/v3', 'secret_key' => '', 'payment_options' => 'card,mobilemoneyuganda', 'redirect_url' => ''],
            'pesapal' => ['base_url' => 'https://cybqa.pesapal.com/pesapalv3/api', 'consumer_key' => '', 'consumer_secret' => '', 'notification_id' => '', 'callback_url' => '', 'country_code' => 'UG'],
            'iotec_pay' => ['base_url' => 'https://pay.iotec.io', 'auth_url' => 'https://id.iotec.io/connect/token', 'client_id' => '', 'client_secret' => '', 'wallet_id' => '', 'currency' => 'UGX', 'transaction_charges_category' => 'ChargeWallet', 'channel' => 'BOQ', 'redirect_url' => ''],
            'generic_aggregator' => [
                'provider_name' => 'Aggregator Name',
                'base_url' => 'https://api.example.com',
                'initiate_path' => '/payments',
                'initiate_method' => 'POST',
                'status_path' => '/payments/{id}',
                'status_by_reference_path' => '/payments/reference/{reference}',
                'callback_url' => '',
                'redirect_url' => '',
                'default_country_code' => '256',
                'auth' => [
                    'type' => 'oauth2_client_credentials',
                    'token_url' => 'https://api.example.com/oauth/token',
                    'client_id' => '',
                    'client_secret' => '',
                ],
                'request_fields' => [
                    'amount' => 'amount', 'currency' => 'currency', 'reference' => 'reference',
                    'method' => 'payment_method', 'phone' => 'phone_number', 'network' => 'network',
                    'email' => 'email', 'name' => 'name', 'callback' => 'callback_url', 'redirect' => 'redirect_url',
                ],
                'response_fields' => [
                    'gateway_transaction_id' => ['id', 'transaction_id', 'data.id'],
                    'status' => ['status', 'data.status'],
                    'checkout_url' => ['checkout_url', 'redirect_url', 'data.checkout_url'],
                    'reference' => ['reference', 'external_id', 'data.reference'],
                    'amount' => ['amount', 'data.amount'],
                    'currency' => ['currency', 'data.currency'],
                ],
                'status_map' => [
                    'successful' => ['success', 'successful', 'completed', 'paid'],
                    'failed' => ['failed', 'cancelled', 'rejected', 'reversed'],
                    'under_review' => ['under_review'],
                    'pending' => ['pending', 'processing', 'initiated'],
                ],
                'method_map' => ['mobile_money' => 'mobile_money', 'card' => 'card'],
                'network_map' => ['mtn' => 'mtn', 'airtel' => 'airtel'],
                'phone_required_methods' => ['mobile_money', 'mtn', 'airtel'],
            ],
            'stripe' => ['mode' => 'test'],
            'bank_transfer' => [],
            'mobile_money' => ['provider' => '', 'mode' => 'test'],
        ];

        $this->configJson = json_encode($templates[$this->form['driver']] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $this->supportedCurrenciesCsv = in_array($this->form['driver'], ['mtn_momo', 'airtel_money', 'flutterwave', 'pesapal', 'iotec_pay', 'generic_aggregator'], true) ? 'UGX' : 'UGX, USD';
        $this->supportedCountriesCsv = 'UG';
        $this->supportedMethodsCsv = match ($this->form['driver']) {
            'mtn_momo' => 'mtn',
            'airtel_money' => 'airtel',
            'flutterwave' => 'card, mobilemoneyuganda',
            'pesapal' => 'card, mobile_money',
            'iotec_pay' => 'mobile_money, card',
            'generic_aggregator' => 'mobile_money, card',
            'bank_transfer' => 'bank_transfer',
            default => '',
        };
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'code' => '',
            'driver' => 'mtn_momo',
            'description' => '',
            'is_active' => true,
            'is_test_mode' => true,
            'webhook_url' => '',
            'payment_timeout_seconds' => 900,
            'refund_settings' => ['allow_refunds' => true, 'refund_window_days' => 30],
        ];
        $this->supportedCurrenciesCsv = 'UGX';
        $this->supportedCountriesCsv = 'UG';
        $this->supportedMethodsCsv = 'mtn';
        $this->configJson = json_encode([
            'base_url' => 'https://sandbox.momodeveloper.mtn.com',
            'subscription_key' => '',
            'api_user' => '',
            'api_key' => '',
            'target_environment' => 'sandbox',
            'currency_override' => 'EUR',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function csv(string $value, bool $uppercase): array
    {
        $items = array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
        return array_map($uppercase ? 'strtoupper' : 'strtolower', $items);
    }

    private function maskedConfig(array $config): array
    {
        foreach ($config as $key => $value) {
            if ($this->isSecretKey((string) $key) && filled($value)) {
                $config[$key] = self::SECRET_MASK;
            } elseif (is_array($value)) {
                $config[$key] = $this->maskedConfig($value);
            }
        }
        return $config;
    }

    private function restoreMaskedSecrets(array $submitted, array $existing): array
    {
        foreach ($submitted as $key => $value) {
            if ($value === self::SECRET_MASK && array_key_exists($key, $existing)) {
                $submitted[$key] = $existing[$key];
            } elseif (is_array($value)) {
                $submitted[$key] = $this->restoreMaskedSecrets($value, is_array($existing[$key] ?? null) ? $existing[$key] : []);
            }
        }
        return $submitted;
    }

    private function isSecretKey(string $key): bool
    {
        $key = strtolower($key);
        return str_contains($key, 'secret') || str_contains($key, 'key') || str_contains($key, 'token') || str_contains($key, 'password');
    }

    public function driverOptions(): array
    {
        return [
            'mtn_momo' => 'MTN MoMo',
            'airtel_money' => 'Airtel Money',
            'flutterwave' => 'Flutterwave',
            'pesapal' => 'Pesapal API 3.0',
            'iotec_pay' => 'Aggregator — ioTec Pay',
            'generic_aggregator' => 'Aggregator — Generic REST API',
            'stripe' => 'Stripe (legacy adapter)',
            'bank_transfer' => 'Bank transfer',
            'mobile_money' => 'Generic mobile money (legacy)',
        ];
    }

    public function render()
    {
        return view('livewire.admin.payment-gateways', [
            'gateways' => PaymentGateway::orderBy('name')->paginate(10),
            'driverOptions' => $this->driverOptions(),
        ]);
    }
}
