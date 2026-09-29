<?php

namespace App\Services;

use App\Models\Boq;
use App\Models\SiteSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds the professional BOQ PDF.
 *
 * Branding (logo, company details and watermark) always comes from the BOQ
 * owner's company profile, frozen into the BOQ the first time it is exported.
 * The person viewing or downloading never changes it.
 */
class BoqPdfService
{
    public function filename(Boq $boq): string
    {
        return Str::slug(($boq->reference ?: 'boq-'.$boq->id).' '.$boq->name, '-').'.pdf';
    }

    public function output(Boq $boq): string
    {
        return $this->pdf($boq)->output();
    }

    public function pdf(Boq $boq): \Barryvdh\DomPDF\PDF
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        $this->freezeBranding($boq);

        $boq->loadMissing(['project', 'owner']);
        $company = $boq->brandingIdentity() ?? [];
        $items = $boq->items()->orderBy('id')->get();

        $lines = $items->map(function ($item) {
            $rate = $item->approved_rate ?? $item->reviewed_rate ?? $item->original_rate ?? $item->ai_suggested_rate;
            $amount = $item->amount !== null && (float) $item->amount > 0
                ? (float) $item->amount
                : (float) $item->quantity * (float) $rate;

            return [
                'code' => $item->item_code,
                'description' => $item->description,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'rate' => $rate,
                'amount' => $amount,
                'priced' => $rate !== null,
            ];
        });

        $subtotal = (float) $lines->sum('amount');
        $taxRate = (float) ($boq->metadata['tax_rate'] ?? SiteSetting::get('tax_rate', 0));
        $taxLabel = (string) ($boq->metadata['tax_label'] ?? SiteSetting::get('tax_label', 'VAT'));
        $tax = $taxRate > 0 ? round($subtotal * $taxRate / 100, 2) : 0.0;

        return Pdf::loadView('pdf.boq', [
            'boq' => $boq,
            'project' => $boq->project,
            'company' => $company,
            'logo' => $this->logoDataUri($company['logo_path'] ?? null),
            'lines' => $lines,
            'subtotal' => $subtotal,
            'taxRate' => $taxRate,
            'taxLabel' => $taxLabel,
            'tax' => $tax,
            'total' => $subtotal + $tax,
            'currency' => $boq->currency ?: \App\Support\Regional::currency(),
            'preparedBy' => $boq->owner?->name,
            'signatures' => $this->signatures($boq),
            'generatedAt' => now(),
        ])
            ->setPaper('a4', 'portrait')
            ->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
    }

    /**
     * Freeze the owner's company identity into the BOQ on first export so later
     * profile changes (or anyone else downloading it) never rebrand old documents.
     */
    private function freezeBranding(Boq $boq): void
    {
        if (! empty($boq->company_snapshot)) {
            return;
        }

        $profile = $boq->owner?->companyProfile;

        if (! $profile) {
            return;
        }

        $snapshot = $profile->snapshot();

        // Keep a private copy of the logo so a later logo change can't alter this BOQ.
        if ($profile->logo_path && Storage::disk('public')->exists($profile->logo_path)) {
            $copy = 'boq-branding/'.$boq->id.'-'.basename($profile->logo_path);
            Storage::disk('local')->put($copy, Storage::disk('public')->get($profile->logo_path));
            $snapshot['logo_path'] = 'local:'.$copy;
        }

        $boq->forceFill(['company_snapshot' => $snapshot])->saveQuietly();
    }

    /**
     * The preparer and client sign-off, with signature images embedded as data
     * URIs (dompdf has remote loading disabled). Missing parts stay null so the
     * PDF prints a blank line to sign by hand.
     *
     * @return array<string, array{name: ?string, title: ?string, date: mixed, image: ?string, remote: bool}>
     */
    private function signatures(Boq $boq): array
    {
        $signed = $boq->signatures()->get()->keyBy('role');
        $block = [];

        foreach (\App\Models\BoqSignature::ROLES as $role) {
            $signature = $signed->get($role);

            $block[$role] = [
                'name' => $signature?->name,
                'title' => $signature?->title,
                'date' => $signature?->signed_at,
                'image' => $signature ? $this->logoDataUri($signature->image_path) : null,
                'remote' => (bool) $signature?->signedRemotely(),
            ];
        }

        return $block;
    }

    /**
     * An image on the public disk (or "local:" path) as a data URI; also used
     * for signature images.
     */
    private function logoDataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            [$disk, $file] = str_starts_with($path, 'local:') ? ['local', substr($path, 6)] : ['public', $path];

            if (! Storage::disk($disk)->exists($file)) {
                return null;
            }

            $bytes = Storage::disk($disk)->get($file);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/png';

            return in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)
                ? 'data:'.$mime.';base64,'.base64_encode($bytes)
                : null;
        } catch (Throwable) {
            return null;
        }
    }
}
