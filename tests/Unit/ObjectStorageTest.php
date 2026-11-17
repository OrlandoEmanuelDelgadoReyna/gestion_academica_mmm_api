<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ObjectStorageException;
use App\Support\CursoPortadaStorage;
use App\Support\ObjectStorage;
use App\Support\OmitS3ObjectAcl;
use App\Support\UsuarioProfilePhotoStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
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
        $this->assertTrue((bool) config('filesystems.disks.media.throw'));
    }

    public function test_media_disk_uses_tigris_compatible_s3_client_options(): void
    {
        $this->assertSame('when_required', config('filesystems.disks.media.request_checksum_calculation'));
        $this->assertSame('when_required', config('filesystems.disks.media.response_checksum_validation'));
        $this->assertSame(
            [OmitS3ObjectAcl::class, 'handle'],
            config('filesystems.disks.media.options.before_upload'),
        );
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

    public function test_store_upload_writes_jpeg_and_png_and_returns_the_object_key(): void
    {
        $this->fakeObjectStorage();

        $jpeg = ObjectStorage::storeUpload(
            UsuarioProfilePhotoStorage::DIRECTORY,
            UploadedFile::fake()->image('avatar.jpg', 200, 200)
        );
        $png = ObjectStorage::storeUpload(
            UsuarioProfilePhotoStorage::DIRECTORY,
            UploadedFile::fake()->image('avatar.png', 200, 200)
        );

        $this->assertTrue(UsuarioProfilePhotoStorage::isManagedPath($jpeg));
        $this->assertTrue(str_ends_with($jpeg, '.jpg'));
        $this->assertTrue(UsuarioProfilePhotoStorage::isManagedPath($png));
        $this->assertTrue(str_ends_with($png, '.png'));
        Storage::disk(ObjectStorage::DISK)->assertExists($jpeg);
        Storage::disk(ObjectStorage::DISK)->assertExists($png);
        $this->assertTemporaryMediaUrl(ObjectStorage::temporaryUrl($jpeg), $jpeg);
    }

    public function test_store_upload_does_not_send_object_visibility(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg');
        $mock = Mockery::mock(Filesystem::class);
        $mock->shouldReceive('putFile')
            ->once()
            ->with(UsuarioProfilePhotoStorage::DIRECTORY, Mockery::type(UploadedFile::class))
            ->andReturn(UsuarioProfilePhotoStorage::DIRECTORY.'/stored.jpg');

        $this->app->make('filesystem')->set(ObjectStorage::DISK, $mock);

        $path = ObjectStorage::storeUpload(UsuarioProfilePhotoStorage::DIRECTORY, $file);

        $this->assertSame(UsuarioProfilePhotoStorage::DIRECTORY.'/stored.jpg', $path);
    }

    public function test_store_upload_throws_storage_error_when_the_adapter_returns_no_path(): void
    {
        $mock = Mockery::mock(Filesystem::class);
        $mock->shouldReceive('putFile')->once()->andReturn(false);
        $this->app->make('filesystem')->set(ObjectStorage::DISK, $mock);

        try {
            ObjectStorage::storeUpload(
                UsuarioProfilePhotoStorage::DIRECTORY,
                UploadedFile::fake()->image('avatar.jpg')
            );
            $this->fail('Expected ObjectStorageException.');
        } catch (ObjectStorageException $exception) {
            $this->assertSame(ObjectStorageException::WRITE_FAILED, $exception->getMessage());
        }
    }

    public function test_temporary_url_failure_returns_empty_string_instead_of_throwing(): void
    {
        $this->fakeObjectStorage();
        $path = 'users/profile/kept.jpg';
        Storage::disk(ObjectStorage::DISK)->put($path, 'avatar-bytes');
        Storage::disk(ObjectStorage::DISK)->buildTemporaryUrlsUsing(
            function (): string {
                throw new \RuntimeException('unable to sign');
            }
        );

        $this->assertSame('', ObjectStorage::temporaryUrl($path));
        $this->assertTrue(ObjectStorage::exists($path));
    }

    public function test_omit_s3_object_acl_removes_acl_from_put_object_command(): void
    {
        $command = new \Aws\Command('PutObject', [
            'ACL' => 'private',
            'Bucket' => 'roomy-briefcase-bspnrii01',
            'Key' => 'users/profile/a.jpg',
        ]);

        OmitS3ObjectAcl::handle($command);

        $this->assertFalse($command->hasParam('ACL'));
        $this->assertSame('roomy-briefcase-bspnrii01', $command['Bucket']);
    }
}
