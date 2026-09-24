<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\UsuarioProfilePhotoStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class UsuarioProfilePhotoApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
        $this->fakeObjectStorage();
    }

    public function test_authenticated_user_without_photo_returns_null_url(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.profile_photo_path', null)
            ->assertJsonPath('data.profile_photo_url', null);
    }

    public function test_user_can_upload_profile_photo(): void
    {
        $usuario = $this->actingAsAdmin();

        $response = $this->post('/api/v1/me/foto-perfil', [
            'foto' => UploadedFile::fake()->image('avatar.jpg', 400, 400),
            'user_id' => 999999,
        ], ['Accept' => 'application/json']);

        $response->assertOk();

        $path = $response->json('data.profile_photo_path');
        $this->assertIsString($path);
        $this->assertTrue(UsuarioProfilePhotoStorage::isManagedPath($path));
        Storage::disk(UsuarioProfilePhotoStorage::DISK)->assertExists($path);
        $this->assertTemporaryMediaUrl($response->json('data.profile_photo_url'), $path);
        $this->assertSame($usuario->id, $response->json('data.id'));

        $this->assertDatabaseHas('usuarios', [
            'id' => $usuario->id,
            'profile_photo_path' => $path,
        ]);
    }

    public function test_authenticated_user_can_update_own_photo(): void
    {
        $usuario = $this->createAlumnoUser('alumno.foto.propia');

        $this->post('/api/v1/me/foto-perfil', [
            'foto' => UploadedFile::fake()->image('mia.png', 320, 320),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $usuario->id)
            ->assertJsonPath('data.profile_photo_path', $usuario->fresh()->profile_photo_path);

        $this->assertNotNull($usuario->fresh()->profile_photo_path);
    }

    public function test_user_cannot_modify_another_user_photo(): void
    {
        $admin = $this->actingAsAdmin();
        $alumno = $this->createAlumnoUser('alumno.foto.ajena');

        Sanctum::actingAs($admin);
        $this->post('/api/v1/me/foto-perfil', [
            'foto' => UploadedFile::fake()->image('admin.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $adminPath = $admin->fresh()->profile_photo_path;
        $this->assertNotNull($adminPath);

        Sanctum::actingAs($alumno);
        $this->post('/api/v1/me/foto-perfil', [
            'foto' => UploadedFile::fake()->image('alumno.jpg'),
            'usuario_id' => $admin->id,
            'user_id' => $admin->id,
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame($adminPath, $admin->fresh()->profile_photo_path);
        $this->assertNotNull($alumno->fresh()->profile_photo_path);
        $this->assertNotSame($adminPath, $alumno->fresh()->profile_photo_path);

        $this->post('/api/v1/usuarios/'.$admin->id.'/foto-perfil', [
            'foto' => UploadedFile::fake()->image('intruso.jpg'),
        ], ['Accept' => 'application/json'])->assertNotFound();

        $this->assertSame($adminPath, $admin->fresh()->profile_photo_path);
    }

    public function test_invalid_file_is_rejected(): void
    {
        $usuario = $this->actingAsAdmin();

        $this->post('/api/v1/me/foto-perfil', [
            'foto' => UploadedFile::fake()->create('malware.exe', 30, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertNull($usuario->fresh()->profile_photo_path);
        $this->assertSame([], Storage::disk(UsuarioProfilePhotoStorage::DISK)->allFiles('users/profile'));
    }

    public function test_oversized_file_is_rejected(): void
    {
        $usuario = $this->actingAsAdmin();

        $this->post('/api/v1/me/foto-perfil', [
            'foto' => UploadedFile::fake()->image('grande.jpg')->size(UsuarioProfilePhotoStorage::MAX_KILOBYTES + 1),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertNull($usuario->fresh()->profile_photo_path);
        $this->assertSame([], Storage::disk(UsuarioProfilePhotoStorage::DISK)->allFiles('users/profile'));
    }

    public function test_delete_profile_photo_works(): void
    {
        $usuario = $this->actingAsAdmin();

        $uploaded = $this->post('/api/v1/me/foto-perfil', [
            'foto' => UploadedFile::fake()->image('borrar.webp'),
        ], ['Accept' => 'application/json']);
        $uploaded->assertOk();
        $path = $uploaded->json('data.profile_photo_path');
        Storage::disk(UsuarioProfilePhotoStorage::DISK)->assertExists($path);

        $this->deleteJson('/api/v1/me/foto-perfil')
            ->assertOk()
            ->assertJsonPath('data.id', $usuario->id)
            ->assertJsonPath('data.profile_photo_path', null)
            ->assertJsonPath('data.profile_photo_url', null);

        Storage::disk(UsuarioProfilePhotoStorage::DISK)->assertMissing($path);
        $this->assertDatabaseHas('usuarios', [
            'id' => $usuario->id,
            'profile_photo_path' => null,
        ]);
    }

    public function test_replacing_photo_deletes_previous_file(): void
    {
        $this->actingAsAdmin();

        $first = $this->post('/api/v1/me/foto-perfil', [
            'foto' => UploadedFile::fake()->image('primera.jpg'),
        ], ['Accept' => 'application/json']);
        $first->assertOk();
        $oldPath = $first->json('data.profile_photo_path');
        Storage::disk(UsuarioProfilePhotoStorage::DISK)->assertExists($oldPath);

        $second = $this->post('/api/v1/me/foto-perfil', [
            'foto' => UploadedFile::fake()->image('segunda.png'),
        ], ['Accept' => 'application/json']);
        $second->assertOk();
        $newPath = $second->json('data.profile_photo_path');

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk(UsuarioProfilePhotoStorage::DISK)->assertExists($newPath);
        Storage::disk(UsuarioProfilePhotoStorage::DISK)->assertMissing($oldPath);
        $this->assertCount(1, Storage::disk(UsuarioProfilePhotoStorage::DISK)->allFiles('users/profile'));
    }

    public function test_profile_photo_url_appears_on_me_after_upload(): void
    {
        $this->actingAsAdmin();

        $uploaded = $this->post('/api/v1/me/foto-perfil', [
            'foto' => UploadedFile::fake()->image('visible.jpg'),
        ], ['Accept' => 'application/json']);
        $uploaded->assertOk();
        $path = $uploaded->json('data.profile_photo_path');

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.profile_photo_path', $path)
            ->assertJsonPath('data.profile_photo_url', $uploaded->json('data.profile_photo_url'));

        $this->assertNotNull($uploaded->json('data.profile_photo_url'));
        $this->assertTemporaryMediaUrl($uploaded->json('data.profile_photo_url'), $path);
    }

    public function test_unauthenticated_user_cannot_upload_photo(): void
    {
        $this->post('/api/v1/me/foto-perfil', [
            'foto' => UploadedFile::fake()->image('anon.jpg'),
        ], ['Accept' => 'application/json'])->assertUnauthorized();
    }

    public function test_existing_profile_photo_path_keeps_the_same_object_key(): void
    {
        $usuario = $this->actingAsAdmin();
        $path = 'users/profile/JbkksTw2rvPTPRC73V0FTHXxMm52U8lB0ZLvMBKG.jpg';
        Storage::disk(UsuarioProfilePhotoStorage::DISK)->put($path, 'avatar-bytes');
        $usuario->forceFill(['profile_photo_path' => $path])->save();

        $response = $this->getJson('/api/v1/me')->assertOk();

        $response->assertJsonPath('data.profile_photo_path', $path);
        $this->assertTemporaryMediaUrl($response->json('data.profile_photo_url'), $path);
        $this->assertDatabaseHas('usuarios', [
            'id' => $usuario->id,
            'profile_photo_path' => $path,
        ]);
    }
}
