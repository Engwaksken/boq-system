<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LegalPageController extends Controller
{
    public function privacy(): View|RedirectResponse
    {
        return $this->show('privacy_policy', 'Privacy Policy', 'fa-user-shield');
    }

    public function terms(): View|RedirectResponse
    {
        return $this->show('terms_of_use', 'Terms of Use', 'fa-file-contract');
    }

    /**
     * Render a legal page from site settings, or redirect when the setting holds a URL.
     */
    private function show(string $key, string $title, string $icon): View|RedirectResponse
    {
        $content = trim((string) SiteSetting::get($key, ''));

        if (filter_var($content, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $content)) {
            return redirect()->away($content);
        }

        return view('legal.show', [
            'title' => $title,
            'icon' => $icon,
            'content' => $content,
        ]);
    }
}
