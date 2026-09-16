# BOQ AI Provider + Payment Aggregator Implementation

## 1. Add the AI Providers route

Edit `routes/web.php`.

Add this import with the other Admin Livewire imports:

```php
use App\Livewire\Admin\AiProviders;
```

Inside the existing Super Admin route group, add:

```php
Route::get('/ai-providers', AiProviders::class)->name('ai-providers');
```

Place it before or after `payment-gateways`.

## 2. Append the CSS below

Append `public/css/boq-ai-payment.css` to your existing `public/css/boq-overrides.css`,
or link the file separately from `layouts/app.blade.php`.

## 3. Run migrations

```bash
php artisan migrate --force
```

## 4. Clear caches

```bash
php artisan optimize:clear
php artisan view:cache
```

## 5. First AI provider

Create it from `/admin/ai-providers`.

Gemini example:

- Provider Name: Google Gemini
- Key: gemini
- Provider Type: Google Gemini
- API Base URL: `https://generativelanguage.googleapis.com`
- Default Model: your currently supported Gemini model
- API Key: enter the key
- Enabled: yes
- Default: yes

## 6. Payment aggregators

Open `/admin/payment-gateways`.

Choose `Aggregator — ioTec Pay` or `Aggregator — Generic REST API`.
The form now exposes structured fields instead of requiring JSON edits.

## 7. Production safety

Do not run `php artisan key:generate` on the existing production system.
Both AI provider credentials and payment gateway configuration depend on the
existing Laravel APP_KEY for decryption.
