<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Concerns\SyncsWhatsappLogin;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Role;

class CreateUser extends CreateRecord
{
    use SyncsWhatsappLogin;

    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->syncWhatsappLoginCredential($data);
    }

    protected function afterCreate(): void
    {
        $roleIds = $this->form->getState()['roles'] ?? [];

        if (! is_array($roleIds)) {
            $roleIds = explode(',', $roleIds);
        }

        $roleNames = Role::whereIn('id', $roleIds)->pluck('name')->toArray();
        $this->record->syncRoles($roleNames);
    }
}
