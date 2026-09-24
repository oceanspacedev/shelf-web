<?php

namespace Tests\Feature;

use App\Filament\Resources\ObChecksheetResource;
use App\Forms\Components\CameraCapture;
use Filament\Forms\Components\FileUpload;
use Filament\Infolists\Components\ImageEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;
use Tests\TestCase;

class ObChecksheetPhotoLayoutTest extends TestCase
{
    public function test_edit_and_view_photos_use_filament_image_preview(): void
    {
        $form = ObChecksheetResource::form(Schema::make($this->makeSchemaLivewire()));
        $components = $this->flatten($form->getComponents(withHidden: true));

        $beforeUpload = $this->photoUpload($components, 'before_photo');
        $afterUpload = $this->photoUpload($components, 'after_photo');

        $this->assertTrue($beforeUpload->isPreviewable());
        $this->assertTrue($afterUpload->isPreviewable());
        $this->assertFalse($beforeUpload->isHidden());
    }

    public function test_create_still_uses_camera_capture_for_the_before_photo(): void
    {
        $form = ObChecksheetResource::form(Schema::make($this->makeSchemaLivewire()));
        $cameras = collect($this->flatten($form->getComponents(withHidden: true)))
            ->filter(fn ($component) => $component instanceof CameraCapture)
            ->values();

        $this->assertTrue(
            $cameras->contains(fn (CameraCapture $component) => $component->getName() === 'before_photo'),
        );
    }

    public function test_view_infolist_uses_filament_image_entries(): void
    {
        $infolist = ObChecksheetResource::infolist(Schema::make($this->makeSchemaLivewire()));
        $entries = collect($this->flatten($infolist->getComponents(withHidden: true)))
            ->filter(fn ($component) => $component instanceof ImageEntry)
            ->map(fn (ImageEntry $entry) => $entry->getName())
            ->all();

        $this->assertContains('before_photo', $entries);
        $this->assertContains('after_photo', $entries);

        $photoSection = collect($infolist->getComponents(withHidden: true))
            ->first(fn ($component) => $component instanceof Section && $component->getHeading() === 'Dokumentasi Foto Kebersihan');
        $this->assertNotNull($photoSection);
    }

    /**
     * @param  array<int, mixed>  $components
     */
    private function photoUpload(array $components, string $name): FileUpload
    {
        foreach ($this->flatten($components) as $component) {
            if ($component instanceof FileUpload && $component->getName() === $name) {
                return $component;
            }
        }

        $this->fail("FileUpload [{$name}] was not found on the OB checksheet form.");
    }

    /**
     * @param  array<int, mixed>  $components
     * @return array<int, mixed>
     */
    private function flatten(array $components): array
    {
        $flat = [];

        foreach ($components as $component) {
            $flat[] = $component;
            if (method_exists($component, 'getChildComponents')) {
                $flat = array_merge($flat, $this->flatten($component->getChildComponents()));
            }
            if (method_exists($component, 'getDefaultChildComponents')) {
                $flat = array_merge($flat, $this->flatten($component->getDefaultChildComponents()));
            }
        }

        return $flat;
    }

    private function makeSchemaLivewire(): Component&HasSchemas
    {
        return new class extends Component implements HasSchemas
        {
            use InteractsWithSchemas;

            public function render(): string
            {
                return '';
            }
        };
    }
}
