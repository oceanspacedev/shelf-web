<?php

namespace App\Http\Controllers;

use App\Models\AssetRequest;
use App\Models\AssetTransfer;
use App\Models\Task;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfController extends Controller
{
    public function downloadAssetTransfer($id)
    {
        $assetTransfer = AssetTransfer::findOrFail($id);
        $this->authorize('view', $assetTransfer);

        $assetTransfer->load('fromUser.jobTitle', 'toUser.jobTitle', 'businessEntity', 'details.asset.category', 'details.asset.brand', 'details.asset.attributes');

        // dd($assetTransfer);

        $status = $assetTransfer->documentCode();

        // Menggunakan nilai dari kolom letterhead, atau default image jika tidak ada
        // $headerImage = $assetTransfer->businessEntity->letterhead
        //     ? asset('storage/' . $assetTransfer->businessEntity->letterhead)
        //     : asset('images/cvcs_kop.png');

        $headerImage = $assetTransfer->businessEntity->letterhead
            ? storage_path('app/public/'.$assetTransfer->businessEntity->letterhead)
            : public_path('images/cvcs_kop.png');

        $letterNumber = $assetTransfer->letter_number;
        $toUserName = strtolower(str_replace(' ', '_', $assetTransfer->toUser->name));
        $toUserJobTitle = $assetTransfer->toUser->jobTitle ? strtolower(str_replace(' ', '_', $assetTransfer->toUser->jobTitle->title)) : 'no_title';

        $fileName = "{$status}_{$letterNumber}_{$toUserName}_{$toUserJobTitle}.pdf";

        $pdf = Pdf::loadView('pdf.asset-transfer', compact('assetTransfer', 'headerImage'));

        return $pdf->download($fileName);

        //  return $pdf->stream($fileName);

        // return view('pdf.asset-transfer', compact('assetTransfer', 'headerImage'));
    }

    public function downloadTaskCompletion($id)
    {
        $task = Task::findOrFail($id);
        $this->authorize('view', $task);

        $task->load('businessEntity');

        // Menggunakan nilai dari kolom letterhead di entitas bisnis terkait, atau default image jika tidak ada
        $headerImage = $task->businessEntity->letterhead
            ? storage_path('app/public/'.$task->businessEntity->letterhead)
            : public_path('images/cvcs_kop.png');

        // Ganti spasi dengan underscore dan ubah jadi huruf kecil semua untuk penamaan file
        $fileName = strtolower(str_replace(' ', '_', $task->name));

        // Siapkan lampiran
        // $attachments = collect(json_decode($task->attachment))->map(function ($image) {
        //     $baseUrl = asset('storage'); // Path dasar menuju file di storage Laravel
        //     // $baseUrl = storage_path('app/public/' . $image);
        //     return "<img src='{$baseUrl}/{$image}' alt='Lampiran'>";
        // })->implode('');

        $attachments = $this->taskAttachmentsHtml($task);

        // Buat PDF dengan kop surat (headerImage), task, dan lampiran
        $pdf = Pdf::loadView('pdf.task-completion', compact('task', 'headerImage', 'attachments'));

        // Download file PDF
        return $pdf->download('berita_acara_pengerjaan_'.$fileName.'.pdf');

        // Stream PDF untuk preview di browser
        // return $pdf->stream('berita_acara_pengerjaan_' . $fileName . '.pdf');

        // return view('pdf.task-completion', compact('task', 'headerImage', 'attachments'));
    }

    public function previewTaskCompletion($id)
    {
        $task = Task::findOrFail($id);
        $this->authorize('view', $task);

        $task->load('businessEntity');

        $headerImage = $task->businessEntity->letterhead
            ? storage_path('app/public/'.$task->businessEntity->letterhead)
            : public_path('images/cvcs_kop.png');

        $fileName = strtolower(str_replace(' ', '_', $task->name));
        $attachments = $this->taskAttachmentsHtml($task);
        $pdf = Pdf::loadView('pdf.task-completion', compact('task', 'headerImage', 'attachments'));

        return $pdf->stream('berita_acara_pengerjaan_'.$fileName.'.pdf');
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
            ? storage_path('app/public/'.$assetRequest->createdAssets->first()->businessEntity->letterhead)
            : public_path('images/cvcs_kop.png');

        $referenceNumber = $assetRequest->reference_number ?? 'PENGADAAN';
        $userName = strtolower(str_replace(' ', '_', $assetRequest->user->name));
        $fileName = "BAP_{$referenceNumber}_{$userName}.pdf";

        $pdf = Pdf::loadView('pdf.pengadaan', compact('assetRequest', 'headerImage'));

        return $pdf->download($fileName);
    }

    private function taskAttachmentsHtml(Task $task): string
    {
        return collect(json_decode($task->attachment))->map(function ($image) {
            $imagePath = storage_path('app/public/'.$image);

            return "<img src='{$imagePath}' alt='Lampiran' style='max-width: 100%; height: auto; margin: 10px 0;'>";
        })->implode('');
    }
}
