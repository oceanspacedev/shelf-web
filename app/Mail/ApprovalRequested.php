<?php

namespace App\Mail;

use App\Models\PublicAssetRequest;
use App\Models\RequestApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApprovalRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PublicAssetRequest $assetRequest,
        public RequestApproval $approval,
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
            subject: "Perlu Persetujuan: {$typeLabel} - {$this->assetRequest->item_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.public_asset_requests.approval-requested',
            with: [
                'assetRequest' => $this->assetRequest,
                'approval' => $this->approval,
                'approvalUrl' => route('asset-requests.show-approval', $this->approval->token),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
