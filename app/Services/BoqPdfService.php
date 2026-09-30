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

    /** The document's HTML before the PDF layout (also handy for checks). */
    public function html(Boq $boq): string
    {
        $this->freezeBranding($boq);

        $boq->loadMissing(['project', 'owner']);
        $company = $boq->brandingIdentity() ?? [];

        return view('pdf.boq', $this->totals($boq) + [
            'boq' => $boq,
            'project' => $boq->project,
            'company' => $company,
            'logo' => $this->logoDataUri($company['logo_path'] ?? null),
            'preparedBy' => $boq->owner?->name,
            'signatures' => $this->signatures($boq),
            'generatedAt' => now(),
        ])->render();
    }

    public function pdf(Boq $boq): \Barryvdh\DomPDF\PDF
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        $pdf = Pdf::loadHTML($this->html($boq))
            ->setPaper('a4', 'portrait')
            ->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);

        // "Page X of Y": the total is only known after layout (CSS counter(pages)
        // is not supported), so it is stamped on every page once rendered.
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $label = __('Page :current of :total', ['current' => '{PAGE_NUM}', 'total' => '{PAGE_COUNT}']);
        $size = 6;
        $width = $dompdf->getFontMetrics()->getTextWidth(str_replace(['{PAGE_NUM}', '{PAGE_COUNT}'], ['00', '00'], $label), $font, $size);
        $canvas->page_text($canvas->get_width() - 27 - $width, $canvas->get_height() - 34.5, $label, $font, $size, [0.39, 0.45, 0.55]);

        return $pdf;
    }

    /**
     * The priced lines and totals exactly as the PDF shows them (also used for
     * the summary on the client signing page).
     *
     * @return array{lines: \Illuminate\Support\Collection, subtotal: float, taxRate: float, taxLabel: string, tax: float, total: float, currency: string}
     */
    public function totals(Boq $boq): array
    {
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

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'taxRate' => $taxRate,
            'taxLabel' => $taxLabel,
            'tax' => $tax,
            'total' => $subtotal + $tax,
            'currency' => $boq->currency ?: \App\Support\Regional::currency(),
        ];
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

        // No "Prepared by" signature on this BOQ yet: use the owner's saved
        // signature from Profile > Signature, so it appears on every BOQ.
        $owner = $boq->owner;
        if (! $signed->has('preparer') && $owner && filled($owner->signature_path)) {
            $block['preparer'] = [
                'name' => $owner->signature_name ?: $owner->name,
                'title' => $owner->signature_title,
                'date' => null,
                'image' => $this->logoDataUri($owner->signature_path),
                'remote' => false,
            ];
        } elseif (! $signed->has('preparer') && $owner) {
            $block['preparer']['name'] = $owner->signature_name ?: $owner->name;
            $block['preparer']['title'] = $owner->signature_title;
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
