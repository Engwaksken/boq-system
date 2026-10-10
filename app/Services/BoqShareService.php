<?php

namespace App\Services;

use App\Mail\BoqShared;
use App\Models\Boq;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Email and WhatsApp/device sharing for BOQs. Shared PDFs always carry the BOQ
 * owner's branding (BoqPdfService), whoever shares them.
 */
class BoqShareService
{
    public const LINK_DAYS = 7;

    public function __construct(private BoqPdfService $pdfs) {}

    public function email(Boq $boq, User $sender, string $to, ?string $subject, ?string $message): void
    {
        $boq->loadMissing('project');

        Mail::to($to)->send(new BoqShared(
            boq: $boq,
            sender: $sender,
            subjectLine: $subject ?: $this->defaultSubject($boq),
            personalMessage: $message,
            pdfBytes: $this->pdfs->output($boq, $sender->name),
            pdfName: $this->pdfs->filename($boq),
        ));
    }

    /**
     * Time-limited, signed link to the PDF that anyone with the link can open,
     * so it can be sent over WhatsApp or any chat app.
     */
    public function link(Boq $boq): string
    {
        return URL::temporarySignedRoute('boqs.shared-pdf', now()->addDays(self::LINK_DAYS), ['boq' => $boq->id]);
    }

    public function message(Boq $boq, ?string $link = null): string
    {
        $boq->loadMissing('project');
        $company = $boq->brandingIdentity()['company_name'] ?? null;

        return trim(implode("\n", array_filter([
            __('Bill of Quantities: :name', ['name' => $boq->name]),
            $boq->project ? __('Project: :project', ['project' => $boq->project->name]) : null,
            __('Reference: :ref', ['ref' => $boq->reference]),
            $company ? __('From: :company', ['company' => $company]) : null,
            $link ? __('Download PDF (valid :days days): :link', ['days' => self::LINK_DAYS, 'link' => $link]) : null,
        ])));
    }

    public function whatsappUrl(Boq $boq): string
    {
        return 'https://wa.me/?text='.rawurlencode($this->message($boq, $this->link($boq)));
    }

    public function defaultSubject(Boq $boq): string
    {
        return __('BOQ :ref – :name', ['ref' => $boq->reference, 'name' => $boq->name]);
    }
}
