<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Field;

class CameraCapture extends Field
{
    protected string $view = 'filament.forms.components.camera-capture';

    protected string $folder = 'ob-checksheets';

    protected string $captureLabel = 'Buka Kamera';

    public function folder(string $folder): static
    {
        $this->folder = $folder;

        return $this;
    }

    public function getFolder(): string
    {
        return $this->folder;
    }

    public function captureLabel(string $label): static
    {
        $this->captureLabel = $label;

        return $this;
    }

    public function getCaptureLabel(): string
    {
        return $this->captureLabel;
    }
}
