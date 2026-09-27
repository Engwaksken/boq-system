<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

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
            'content' => $content === '' ? null : self::toSafeHtml($content),
        ]);
    }

    /**
     * Admin-authored legal text: HTML is allowed but sanitised (no scripts, event
     * handlers, iframes or javascript: links); plain text keeps its line breaks.
     */
    public static function toSafeHtml(string $content): HtmlString
    {
        if ($content === strip_tags($content)) {
            return new HtmlString(nl2br(e($content)));
        }

        $sanitizer = new HtmlSanitizer(
            (new HtmlSanitizerConfig())
                ->allowSafeElements()
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->allowRelativeLinks()
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->withMaxInputLength(200_000)
        );

        return new HtmlString($sanitizer->sanitize($content));
    }
}
