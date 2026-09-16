# Hardware Scanner Fix

This patch fixes:

1. Empty category dropdown.
2. Missing HardwareCategory model.
3. Admin category add/edit/activate/delete management.
4. Missing `hardware:fetch-daily` command that caused the Run Daily Fetch 500.
5. Scanner calling a missing `fetchPricesForCategory()` service method.
6. Daily fetch using the configured database AI provider instead of the old hard-coded Gemini request.
7. CSV import robustness.
8. CSV template download.
9. 06:00 Africa/Kampala daily schedule.

## Apply

Copy the files into the BOQ Laravel project.

Append `public/css/hardware-scanner-admin.css` to the bottom of:

`public/css/boq-overrides.css`

Then run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan route:clear
php artisan view:clear

php artisan list | grep hardware
php artisan schedule:list

php artisan view:cache
php artisan config:cache
```

Expected command:

`hardware:fetch-daily`

Manual test:

```bash
php artisan hardware:fetch-daily --organisation=1 --location=Kampala --limit=1
```

Use the correct organisation ID.

## Important

The daily hardware fetch now depends on at least one enabled AI Provider configured at:

`/admin/ai-providers`

and a default provider should be selected.

Do not run `php artisan key:generate` on an existing production system.
