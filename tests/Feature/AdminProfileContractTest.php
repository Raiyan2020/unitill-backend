<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProfileContractTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_upload_and_remove_a_profile_photo(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->post('/api/admin/profile/photo', [
            'image' => UploadedFile::fake()->image('profile.png', 200, 200),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', true);

        $admin->refresh();
        $this->assertNotNull($admin->image);
        Storage::disk('public')->assertExists($admin->image);

        $this->getJson('/api/admin/profile')
            ->assertOk()
            ->assertJsonPath('data.image', $admin->image)
            ->assertJsonPath('data.image_url', asset('storage/'.$admin->image));

        $path = $admin->image;

        $this->deleteJson('/api/admin/profile/photo')
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertNull($admin->fresh()->image);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_profile_photo_must_be_an_image(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->admin());

        $this->post('/api/admin/profile/photo', [
            'image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('status', false);
    }

    private function admin(): Admin
    {
        return Admin::create([
            'name' => 'Profile Admin',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]);
    }
}
