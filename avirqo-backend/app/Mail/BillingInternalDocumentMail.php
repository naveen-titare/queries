<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class BillingInternalDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $documentType,
        public int $documentId,
        public string $pdfContent,
        public ?string $userMessage = null,
        public ?string $documentNumber = null,
        public ?string $companyName = null,
    ) {}

    public function build()
    {
        $label = Str::headline(str_replace('_', ' ', $this->documentType));
        $subject = $this->documentType === 'proforma_invoice' && $this->documentNumber && $this->companyName
            ? "Proforma Invoice - {$this->documentNumber} - {$this->companyName}"
            : "Internal {$label} document";

        $heading = $this->documentType === 'proforma_invoice' && $this->documentNumber && $this->companyName
            ? "Proforma Invoice - {$this->documentNumber} for {$this->companyName}"
            : $label;

        return $this->subject($subject)
            ->view('emails.billing-internal-document', [
                'label' => $label,
                'heading' => $heading,
                'messageText' => $this->userMessage,
            ])
            ->attachData($this->pdfContent, "{$this->documentType}-{$this->documentId}.pdf", [
                'mime' => 'application/pdf',
            ]);
    }
}
