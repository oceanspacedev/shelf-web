<?php

namespace App\Mail;

use App\Models\PublicAssetRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PublicAssetRequestStatusChanged extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PublicAssetRequest $assetRequest,
    ) {}

    public function envelope(): Envelope
    {
        $statusLabel = $this->assetRequest->status->label();

        return new Envelope(
            subject: "Update Status Pengajuan Aset: {$statusLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.public_asset_requests.status-changed',
            with: [
                'assetRequest' => $this->assetRequest,
                'detailUrl' => route('asset-requests.success', $this->assetRequest->uuid),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
