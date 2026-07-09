<?php

namespace App\Filament\Pages\Auth;

use Apriansyahrs\MekayaTheme\Auth\MekayaLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class Login extends MekayaLogin
{
    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('login')
                    ->label('Username or Email')
                    ->required()
                    ->autocomplete(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username' => $data['login'],
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }
}
