<?php

namespace App\Mail;

use App\Models\PublicAssetRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PublicAssetRequestSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PublicAssetRequest $assetRequest,
    ) {}

    public function envelope(): Envelope
    {
        $typeLabels = [
            'pengadaan_aset' => 'Pengadaan Aset',
            'perbaikan_aset' => 'Perbaikan Aset',
            'penarikan_aset' => 'Penarikan Aset',
        ];

        $typeLabel = $typeLabels[$this->assetRequest->request_type] ?? 'Request Aset';

        return new Envelope(
            subject: "Konfirmasi: {$typeLabel} - {$this->assetRequest->item_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.public_asset_requests.submitted',
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
