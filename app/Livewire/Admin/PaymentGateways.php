<?php

namespace App\Livewire\Admin;

use App\Models\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
    public string $supportedCurrenciesCsv = 'UGX';
    public string $supportedCountriesCsv = 'UG';
    public string $supportedMethodsCsv = 'mobile_money, card';

    public array $form = [];
    public array $config = [];

    public function mount(): void
    {
        $this->resetForm();
    }

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
            'is_default' => $gateway->is_default,
            'is_test_mode' => $gateway->is_test_mode,
            'webhook_url' => $gateway->webhook_url,
            'payment_timeout_seconds' => $gateway->payment_timeout_seconds,
        ];

        $this->config = $this->maskedConfig($gateway->config ?? []);
        $this->supportedCurrenciesCsv = implode(', ', $gateway->supported_currencies ?? []);
        $this->supportedCountriesCsv = implode(', ', $gateway->supported_countries ?? []);
        $this->supportedMethodsCsv = implode(', ', $gateway->supported_methods ?? []);
        $this->showForm = true;
    }

    public function updatedFormDriver(): void
    {
        $this->loadDriverTemplate();
    }

    public function save(): void
    {
        $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:50', Rule::unique('payment_gateways', 'code')->ignore($this->editingId)],
            'form.driver' => ['required', Rule::in(array_keys($this->driverOptions()))],
            'form.description' => ['nullable', 'string'],
            'form.is_active' => ['boolean'],
            'form.is_default' => ['boolean'],
            'form.is_test_mode' => ['boolean'],
            'form.webhook_url' => ['nullable', 'url'],
            'form.payment_timeout_seconds' => ['required', 'integer', 'min:30', 'max:86400'],
            'supportedCurrenciesCsv' => ['nullable', 'string', 'max:255'],
            'supportedCountriesCsv' => ['nullable', 'string', 'max:255'],
            'supportedMethodsCsv' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () {
            $gateway = $this->editingId
                ? PaymentGateway::findOrFail($this->editingId)
                : new PaymentGateway();

            $config = $this->restoreMaskedSecrets($this->config, $gateway->config ?? []);

            if ($this->form['is_default']) {
                PaymentGateway::query()
                    ->when($gateway->exists, fn ($q) => $q->whereKeyNot($gateway->getKey()))
                    ->update(['is_default' => false]);
            }

            $gateway->fill(array_merge($this->form, [
                'config' => $config,
                'supported_currencies' => $this->csv($this->supportedCurrenciesCsv, true),
                'supported_countries' => $this->csv($this->supportedCountriesCsv, true),
                'supported_methods' => $this->csv($this->supportedMethodsCsv, false),
            ]))->save();
        });

        session()->flash('message', $this->editingId ? 'Payment gateway updated successfully.' : 'Payment gateway created successfully.');
        $this->cancel();
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetForm();
        $this->resetValidation();
    }

    public function toggleActive(int $id): void
    {
        $gateway = PaymentGateway::findOrFail($id);
        $gateway->update(['is_active' => ! $gateway->is_active]);
        session()->flash('message', 'Payment gateway status updated.');
    }

    public function setDefault(int $id): void
    {
        DB::transaction(function () use ($id) {
            PaymentGateway::query()->update(['is_default' => false]);

            PaymentGateway::findOrFail($id)->update([
                'is_default' => true,
                'is_active' => true,
            ]);
        });

        session()->flash('message', 'Default payment gateway updated.');
    }

    public function loadDriverTemplate(): void
    {
        $this->config = $this->templateFor($this->form['driver']);

        $this->supportedCurrenciesCsv = in_array(
            $this->form['driver'],
            ['mtn_momo', 'airtel_money', 'flutterwave', 'pesapal', 'iotec_pay', 'generic_aggregator'],
            true
        ) ? 'UGX' : 'UGX, USD';

        $this->supportedCountriesCsv = 'UG';

        $this->supportedMethodsCsv = match ($this->form['driver']) {
            'mtn_momo' => 'mtn',
            'airtel_money' => 'airtel',
            'flutterwave' => 'card, mobilemoneyuganda',
            'pesapal' => 'card, mobile_money',
            'iotec_pay', 'generic_aggregator' => 'mobile_money, card',
            'bank_transfer' => 'bank_transfer',
            default => '',
        };
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
            'stripe' => 'Stripe',
            'bank_transfer' => 'Bank transfer',
            'mobile_money' => 'Generic mobile money',
        ];
    }

    private function resetForm(): void
    {
        $this->form = [
            'name' => '',
            'code' => '',
            'driver' => 'iotec_pay',
            'description' => '',
            'is_active' => true,
            'is_default' => false,
            'is_test_mode' => true,
            'webhook_url' => '',
            'payment_timeout_seconds' => 900,
        ];

        $this->supportedCurrenciesCsv = 'UGX';
        $this->supportedCountriesCsv = 'UG';
        $this->supportedMethodsCsv = 'mobile_money, card';
        $this->config = $this->templateFor('iotec_pay');
    }

    private function templateFor(string $driver): array
    {
        return match ($driver) {
            'iotec_pay' => [
                'provider_name' => 'ioTec Pay',
                'provider_type' => 'aggregator',
                'base_url' => 'https://pay.iotec.io',
                'token_url' => 'https://id.iotec.io/connect/token',
                'collect_url' => '',
                'status_url' => '',
                'client_id' => '',
                'client_secret' => '',
                'wallet_guid' => '',
                'callback_url' => '',
                'return_url' => '',
                'currency' => 'UGX',
                'channel' => 'BOQ',
                'transaction_charges_category' => 'ChargeWallet',
                'supports_collection' => true,
                'supports_disbursement' => false,
                'supports_mtn' => true,
                'supports_airtel' => true,
            ],
            'generic_aggregator' => [
                'provider_name' => 'Aggregator Name',
                'provider_type' => 'aggregator',
                'base_url' => 'https://api.example.com',
                'token_url' => '',
                'collect_url' => '/payments',
                'status_url' => '/payments/{id}',
                'status_by_reference_url' => '/payments/reference/{reference}',
                'callback_url' => '',
                'return_url' => '',
                'auth_type' => 'oauth2_client_credentials',
                'client_id' => '',
                'client_secret' => '',
                'api_key' => '',
                'bearer_token' => '',
                'country_code' => '256',
                'currency' => 'UGX',
                'supports_collection' => true,
                'supports_disbursement' => false,
                'supports_mtn' => true,
                'supports_airtel' => true,
                'request_fields' => [
                    'amount' => 'amount',
                    'currency' => 'currency',
                    'reference' => 'reference',
                    'phone' => 'phone_number',
                    'network' => 'network',
                    'email' => 'email',
                    'name' => 'name',
                    'callback' => 'callback_url',
                ],
                'response_fields' => [
                    'transaction_id' => 'data.id',
                    'status' => 'data.status',
                    'checkout_url' => 'data.checkout_url',
                    'reference' => 'data.reference',
                    'amount' => 'data.amount',
                    'currency' => 'data.currency',
                ],
            ],
            'mtn_momo' => [
                'base_url' => 'https://sandbox.momodeveloper.mtn.com',
                'subscription_key' => '',
                'api_user' => '',
                'api_key' => '',
                'target_environment' => 'sandbox',
            ],
            'airtel_money' => [
                'base_url' => 'https://openapiuat.airtel.africa',
                'client_id' => '',
                'client_secret' => '',
                'country' => 'UG',
                'currency' => 'UGX',
            ],
            'flutterwave' => [
                'base_url' => 'https://api.flutterwave.com/v3',
                'secret_key' => '',
                'payment_options' => 'card,mobilemoneyuganda',
                'redirect_url' => '',
            ],
            'pesapal' => [
                'base_url' => 'https://cybqa.pesapal.com/pesapalv3/api',
                'consumer_key' => '',
                'consumer_secret' => '',
                'notification_id' => '',
                'callback_url' => '',
                'country_code' => 'UG',
            ],
            'stripe' => ['secret_key' => '', 'publishable_key' => ''],
            'mobile_money' => ['provider' => '', 'mode' => 'test'],
            default => [],
        };
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
                $submitted[$key] = $this->restoreMaskedSecrets(
                    $value,
                    is_array($existing[$key] ?? null) ? $existing[$key] : []
                );
            }
        }

        return $submitted;
    }

    private function isSecretKey(string $key): bool
    {
        $key = strtolower($key);

        return str_contains($key, 'secret')
            || str_contains($key, 'key')
            || str_contains($key, 'token')
            || str_contains($key, 'password');
    }

    public function render()
    {
        return view('livewire.admin.payment-gateways', [
            'gateways' => PaymentGateway::orderByDesc('is_default')->orderBy('name')->paginate(10),
            'driverOptions' => $this->driverOptions(),
            'stats' => [
                'gateways' => PaymentGateway::count(),
                'active' => PaymentGateway::where('is_active', true)->count(),
                'aggregators' => PaymentGateway::whereIn('driver', ['iotec_pay', 'generic_aggregator'])->count(),
                'default' => PaymentGateway::where('is_default', true)->value('name') ?? 'None',
            ],
        ]);
    }
}
