<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CameraUploadTest extends TestCase
{
    public function test_guest_cannot_upload_camera_photo(): void
    {
        $response = $this->postJson(route('admin.camera-upload'), [
            'folder' => 'ob-checksheets/before',
            'image_data' => 'data:image/jpeg;base64,' . base64_encode('fake-image-content'),
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_upload_base64_camera_snapshot(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        // 1x1 transparent GIF base64
        $base64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response = $this->actingAs($user)->postJson(route('admin.camera-upload'), [
            'folder' => 'ob-checksheets/before',
            'image_data' => $base64,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['success', 'path', 'url']);
        $this->assertTrue($response->json('success'));
        $this->assertStringStartsWith('ob-checksheets/before/', $response->json('path'));

        Storage::disk('public')->assertExists($response->json('path'));
    }

    public function test_authenticated_user_can_upload_file_from_native_camera(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('camera_snap.jpg', 640, 480);

        $response = $this->actingAs($user)->post(route('admin.camera-upload'), [
            'folder' => 'ob-checksheets/after',
            'photo' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['success', 'path', 'url']);
        $this->assertTrue($response->json('success'));
        $this->assertStringStartsWith('ob-checksheets/after/', $response->json('path'));

        Storage::disk('public')->assertExists($response->json('path'));
    }
}
