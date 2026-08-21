<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_an_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post('/profile/avatar', [
                'avatar' => UploadedFile::fake()->image('avatar.jpg', 400, 400),
            ]);

        $response->assertOk()->assertJsonStructure(['avatar_url']);

        $path = str_replace('/storage/', '', $user->fresh()->avatar_url);
        Storage::disk('public')->assertExists($path);
    }

    public function test_avatar_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/')->post('/profile/avatar', [
                'avatar' => 'não é uma imagem',
            ]);

        $response->assertRedirect('/')->assertSessionHasErrors('avatar');
    }

    public function test_authenticated_user_can_restore_default_avatar(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/1/avatar.jpg', 'image');
        $user = User::factory()->create(['avatar_url' => '/storage/avatars/1/avatar.jpg']);

        $this->actingAs($user)->deleteJson('/profile/avatar')
            ->assertOk()->assertJson(['avatar_url' => null]);

        $this->assertNull($user->fresh()->avatar_url);
        Storage::disk('public')->assertMissing('avatars/1/avatar.jpg');
    }
}
