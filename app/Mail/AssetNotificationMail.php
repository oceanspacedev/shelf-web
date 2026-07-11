<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class AssetNotificationMail extends Mailable
{
    /**
     * @param  array{
     *     form_title: string,
     *     intro: string,
     *     fields: array<string, string|int>,
     *     approvals: array<int, array{approver: string, title: string, status: string, comments: string, timestamp: string}>,
     *     cta_label: string,
     *     cta_url: string,
     *     cta_variant: string
     * }  $email
     */
    public function __construct(
        public string $subjectLine,
        public string $body,
        public array $email = [],
    ) {}

    public function build(): self
    {
        $mail = $this
            ->subject($this->subjectLine)
            ->text('emails.asset-notification');

        if ($this->email !== []) {
            $mail->view('emails.asset-notification-html', [
                'formTitle' => $this->email['form_title'] ?? 'FORM PENGAJUAN ASET',
                'intro' => $this->email['intro'] ?? '',
                'fields' => $this->email['fields'] ?? [],
                'approvals' => $this->email['approvals'] ?? [],
                'ctaLabel' => $this->email['cta_label'] ?? 'Open',
                'ctaUrl' => $this->email['cta_url'] ?? '#',
                'ctaVariant' => $this->email['cta_variant'] ?? 'progress',
            ]);
        }

        return $mail;
    }
}
