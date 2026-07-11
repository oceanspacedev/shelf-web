<?php

namespace App\Services;

use App\Mail\AssetNotificationMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AssetNotificationService
{
    /**
     * Send email and WhatsApp notifications to a user.
     *
     * @param  string|array{whatsapp: string, email?: array<string, mixed>}  $message
     * @return array{whatsapp: bool|null, email: bool|null}
     */
    public static function send(User $user, string $subject, string|array $message): array
    {
        $whatsappMessage = is_array($message) ? (string) ($message['whatsapp'] ?? '') : $message;
        $emailPayload = is_array($message) ? ($message['email'] ?? []) : [];

        $result = [
            'whatsapp' => null,
            'email' => null,
        ];

        // 1. Send WhatsApp if number exists
        if ($user->whatsapp_number) {
            $result['whatsapp'] = WhatsappService::send($user->whatsapp_number, $whatsappMessage);

            if ($result['whatsapp'] === false) {
                Log::error('Notifikasi WhatsApp pengajuan aset gagal terkirim.', [
                    'user_id' => $user->id,
                    'subject' => $subject,
                ]);
            }
        } else {
            Log::warning('Notifikasi WhatsApp pengajuan aset dilewati: nomor penerima kosong.', [
                'user_id' => $user->id,
            ]);
        }

        // 2. Send Email if email exists
        if ($user->email && self::isSafeEmail($user->email)) {
            try {
                Mail::to($user->email)->send(new AssetNotificationMail($subject, $whatsappMessage, $emailPayload));
                $result['email'] = true;
            } catch (Throwable $e) {
                $result['email'] = false;
                Log::error('Gagal mengirim email notifikasi pengajuan aset: '.$e->getMessage(), [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }
        } elseif ($user->email) {
            $result['email'] = false;
            Log::warning('Email notifikasi pengajuan aset dilewati karena alamat tidak aman.', [
                'user_id' => $user->id,
            ]);
        }

        return $result;
    }

    private static function isSafeEmail(string $email): bool
    {
        return preg_match('/[\r\n]/', $email) !== 1
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
