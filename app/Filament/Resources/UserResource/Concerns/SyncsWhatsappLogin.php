<?php

namespace App\Filament\Resources\UserResource\Concerns;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;

trait SyncsWhatsappLogin
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function syncWhatsappLoginCredential(array $data): array
    {
        $actor = Auth::user();
        $canManageLogin = $actor?->hasRole('super_admin') || $actor?->hasRole('admin');

        if (! $canManageLogin) {
            unset($data['whatsapp_login_number']);

            return $data;
        }

        $data['whatsapp_login_number'] = PhoneNumber::canonical($data['whatsapp_number'] ?? null);

        return $data;
    }
}
