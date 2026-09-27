# BOQ subscriptions + form/modal fixes

This update fixes the `/subscriptions` page and standardises form controls.

## Added
- **Subscriptions** / **Available Plans** tabs
- search
- status filter
- period filter
- 10 / 25 / 50 / 100 pagination
- row multi-selection and select-page
- bulk cancellation with confirmation modal
- available-plan search and billing-period filter
- Livewire-only plan selection and cancellation modals
- real `subscribe` / `cancel` methods that were previously missing
- consistent 42px form controls and readable placeholders

## Apply
Copy these two files over the existing files:

- `app/Livewire/Subscriptions/Index.php`
- `resources/views/livewire/subscriptions/index.blade.php`

Then **append** the contents of:

- `resources/css/subscriptions-form-fixes.css`

to your existing `resources/css/app.css`.

Do not replace the whole existing `app.css` with the small CSS fixes file.

## Local build and push

```powershell
cd D:\projects\BOQ_system\boq-system
npm run build
php artisan optimize:clear

git add app/Livewire/Subscriptions/Index.php resources/views/livewire/subscriptions/index.blade.php resources/css/app.css
git add -f public/build
git commit -m "feat(subscriptions): add tabs filters pagination selection and Livewire modals"
git push origin main
```

## Live server

```bash
git fetch origin
git checkout main
git merge --ff-only origin/main   # fails safely if the server has local edits
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

No `npm` command is needed on live if `public/build` is committed.

If changes still do not appear:

- Confirm `git log -1 --oneline` on the server matches the latest commit on GitHub.
- Confirm the web server's document root points at this checkout's `public/` directory.
- Restart PHP (cPanel: *MultiPHP Manager* / *Select PHP Version*, or `sudo systemctl reload php*-fpm`) to clear OPcache.
