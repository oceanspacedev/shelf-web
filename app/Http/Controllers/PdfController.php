<?php

namespace App\Http\Controllers;

use App\Models\AssetRequest;
use App\Models\AssetTransfer;
use App\Models\Task;
use App\Support\StoredFile;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfController extends Controller
{
    public function downloadAssetTransfer($id)
    {
        $assetTransfer = AssetTransfer::findOrFail($id);
        $this->authorize('view', $assetTransfer);

        $assetTransfer->load('fromUser.jobTitle', 'toUser.jobTitle', 'businessEntity', 'details.asset.category', 'details.asset.brand', 'details.asset.attributes');

        $status = $assetTransfer->documentCode();

        $headerImage = $assetTransfer->businessEntity->letterhead
            ? StoredFile::imageDataUri('public', $assetTransfer->businessEntity->letterhead)
            : public_path('images/cvcs_kop.png');

        $letterNumber = $assetTransfer->letter_number;
        $toUserName = strtolower(str_replace(' ', '_', $assetTransfer->toUser->name));
        $toUserJobTitle = $assetTransfer->toUser->jobTitle ? strtolower(str_replace(' ', '_', $assetTransfer->toUser->jobTitle->title)) : 'no_title';

        $fileName = $this->safeDownloadFilename("{$status}_{$letterNumber}_{$toUserName}_{$toUserJobTitle}.pdf");

        $pdf = Pdf::loadView('pdf.asset-transfer', compact('assetTransfer', 'headerImage'));

        return $pdf->download($fileName);
    }

    public function downloadTaskCompletion($id)
    {
        $task = Task::findOrFail($id);
        $this->authorize('view', $task);

        $task->load('businessEntity');

        $headerImage = $task->businessEntity->letterhead
            ? StoredFile::imageDataUri('public', $task->businessEntity->letterhead)
            : public_path('images/cvcs_kop.png');

        $fileName = strtolower(str_replace(' ', '_', $task->name));
        $attachments = $this->taskAttachmentsHtml($task);
        $pdf = Pdf::loadView('pdf.task-completion', compact('task', 'headerImage', 'attachments'));

        return $pdf->download($this->safeDownloadFilename('berita_acara_pengerjaan_'.$fileName.'.pdf'));
    }

    public function previewTaskCompletion($id)
    {
        $task = Task::findOrFail($id);
        $this->authorize('view', $task);

        $task->load('businessEntity');

        $headerImage = $task->businessEntity->letterhead
            ? StoredFile::imageDataUri('public', $task->businessEntity->letterhead)
            : public_path('images/cvcs_kop.png');

        $fileName = strtolower(str_replace(' ', '_', $task->name));
        $attachments = $this->taskAttachmentsHtml($task);
        $pdf = Pdf::loadView('pdf.task-completion', compact('task', 'headerImage', 'attachments'));

        return $pdf->stream($this->safeDownloadFilename('berita_acara_pengerjaan_'.$fileName.'.pdf'));
    }

    /**
     * Berita Acara Pengadaan: dokumen tindak lanjut operator dari pengajuan
     * pengadaan yang sudah disetujui & di-fulfill (aset sudah dibuat).
     */
    public function downloadPengadaan($id)
    {
        $assetRequest = AssetRequest::findOrFail($id);
        $this->authorize('view', $assetRequest);

        // BA Pengadaan hanya bermakna bila pengajuan sudah fulfilled dan ada aset
        // yang dibuat darinya. Tanpa guard ini, URL bisa diakses langsung untuk
        // pengadaan belum selesai -> BA dengan tabel aset kosong.
        abort_unless($assetRequest->is_fulfilled && $assetRequest->createdAssets->isNotEmpty(), 404);

        $assetRequest->load([
            'user.jobTitle',
            'division',
            'fulfilledBy.jobTitle',
            'createdAssets.category',
            'createdAssets.brand',
            'createdAssets.assetLocation',
            'createdAssets.businessEntity',
            'createdAssets.attributes',
        ]);

        $headerImage = $assetRequest->createdAssets->first()?->businessEntity?->letterhead
            ? StoredFile::imageDataUri('public', $assetRequest->createdAssets->first()->businessEntity->letterhead)
            : public_path('images/cvcs_kop.png');

        $referenceNumber = $assetRequest->reference_number ?? 'PENGADAAN';
        $userName = strtolower(str_replace(' ', '_', $assetRequest->user->name));
        $fileName = $this->safeDownloadFilename("BAP_{$referenceNumber}_{$userName}.pdf");

        $pdf = Pdf::loadView('pdf.pengadaan', compact('assetRequest', 'headerImage'));

        return $pdf->download($fileName);
    }

    /**
     * Content-Disposition filenames cannot contain "/" or "\".
     * Letter numbers like "221218.CS/000297" must be sanitized.
     */
    private function safeDownloadFilename(string $fileName): string
    {
        $fileName = str_replace(['/', '\\'], '-', $fileName);
        $fileName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $fileName) ?: 'document.pdf';

        return $fileName;
    }

    private function taskAttachmentsHtml(Task $task): string
    {
        return collect(json_decode($task->attachment))->map(function ($image) {
            $imagePath = StoredFile::imageDataUri('public', $image);

            return "<img src='{$imagePath}' alt='Lampiran' style='max-width: 100%; height: auto; margin: 10px 0;'>";
        })->implode('');
    }
}
