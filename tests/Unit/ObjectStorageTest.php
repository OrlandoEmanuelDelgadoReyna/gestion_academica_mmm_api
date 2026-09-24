<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CursoPortadaStorage;
use App\Support\ObjectStorage;
use App\Support\UsuarioProfilePhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ObjectStorageTest extends TestCase
{
    public function test_media_disk_is_dedicated_and_not_the_default_filesystem(): void
    {
        $this->assertSame('media', ObjectStorage::DISK);
        $this->assertSame('media', CursoPortadaStorage::DISK);
        $this->assertSame('media', UsuarioProfilePhotoStorage::DISK);
        $this->assertSame('local', config('filesystems.default'));
        $this->assertSame('s3', config('filesystems.disks.media.driver'));
        $this->assertSame('private', config('filesystems.disks.media.visibility'));
    }

    public function test_railway_bucket_env_names_are_wired_on_the_media_disk(): void
    {
        config([
            'filesystems.disks.media.key' => 'from-access-key',
            'filesystems.disks.media.secret' => 'from-secret',
            'filesystems.disks.media.region' => 'auto',
            'filesystems.disks.media.bucket' => 'from-s3-bucket-name',
            'filesystems.disks.media.endpoint' => 'https://bucket.endpoint.example',
            'filesystems.disks.media.use_path_style_endpoint' => true,
        ]);

        $this->assertSame('from-access-key', config('filesystems.disks.media.key'));
        $this->assertSame('from-secret', config('filesystems.disks.media.secret'));
        $this->assertSame('auto', config('filesystems.disks.media.region'));
        $this->assertSame('from-s3-bucket-name', config('filesystems.disks.media.bucket'));
        $this->assertSame('https://bucket.endpoint.example', config('filesystems.disks.media.endpoint'));
        $this->assertTrue((bool) config('filesystems.disks.media.use_path_style_endpoint'));
    }

    public function test_portada_url_is_temporary_and_keeps_existing_object_key(): void
    {
        $this->fakeObjectStorage();
        $path = 'cursos/portadas/zKDFcXpb1Kr28TS9ZI6qVGnQYbyR7473k7AWwDjQ.jpg';
        Storage::disk(ObjectStorage::DISK)->put($path, 'cover-bytes');

        $url = CursoPortadaStorage::url($path);

        $this->assertTemporaryMediaUrl($url, $path);
        $this->assertTrue(ObjectStorage::exists($path));
    }

    public function test_profile_photo_url_is_temporary_and_keeps_existing_object_key(): void
    {
        $this->fakeObjectStorage();
        $path = 'users/profile/JbkksTw2rvPTPRC73V0FTHXxMm52U8lB0ZLvMBKG.jpg';
        Storage::disk(ObjectStorage::DISK)->put($path, 'avatar-bytes');

        $url = UsuarioProfilePhotoStorage::url($path);

        $this->assertTemporaryMediaUrl($url, $path);
        $this->assertTrue(ObjectStorage::exists($path));
    }

    public function test_upload_stores_on_the_media_disk_and_delete_removes_the_object(): void
    {
        $this->fakeObjectStorage();
        $path = CursoPortadaStorage::storeUpload(
            UploadedFile::fake()->image('portada.jpg', 640, 360)
        );

        $this->assertTrue(CursoPortadaStorage::isManagedPath($path));
        Storage::disk(ObjectStorage::DISK)->assertExists($path);

        CursoPortadaStorage::deleteManaged($path);

        Storage::disk(ObjectStorage::DISK)->assertMissing($path);
    }

    public function test_replacing_a_profile_photo_deletes_the_previous_object(): void
    {
        $this->fakeObjectStorage();
        $first = UsuarioProfilePhotoStorage::storeUpload(
            UploadedFile::fake()->image('primera.jpg')
        );
        $second = UsuarioProfilePhotoStorage::storeUpload(
            UploadedFile::fake()->image('segunda.png')
        );

        $this->assertNotSame($first, $second);
        Storage::disk(ObjectStorage::DISK)->assertExists($second);

        UsuarioProfilePhotoStorage::deleteManaged($first);

        Storage::disk(ObjectStorage::DISK)->assertMissing($first);
        Storage::disk(ObjectStorage::DISK)->assertExists($second);
        $this->assertCount(1, Storage::disk(ObjectStorage::DISK)->allFiles('users/profile'));
    }

    public function test_unknown_paths_do_not_generate_urls_or_delete_objects(): void
    {
        $this->fakeObjectStorage();
        Storage::disk(ObjectStorage::DISK)->put('otros/archivo.jpg', 'keep-me');

        $this->assertNull(CursoPortadaStorage::url('otros/archivo.jpg'));
        $this->assertNull(UsuarioProfilePhotoStorage::url('/storage/users/profile/a.jpg'));

        CursoPortadaStorage::deleteManaged('otros/archivo.jpg');
        UsuarioProfilePhotoStorage::deleteManaged('https://example.test/users/profile/a.jpg');

        Storage::disk(ObjectStorage::DISK)->assertExists('otros/archivo.jpg');
    }
}
