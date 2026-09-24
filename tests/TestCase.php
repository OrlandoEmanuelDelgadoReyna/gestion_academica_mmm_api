<?php

namespace Tests;

use App\Support\ObjectStorage;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected function fakeObjectStorage(): void
    {
        Storage::fake(ObjectStorage::DISK);
        Storage::disk(ObjectStorage::DISK)->buildTemporaryUrlsUsing(
            function (string $path, DateTimeInterface $expiration, array $options = []): string {
                return 'https://media.test/'.$path.'?X-Amz-Expires='.$expiration->getTimestamp().'&X-Amz-Signature=test';
            }
        );
    }

    protected function assertTemporaryMediaUrl(mixed $url, string $path): void
    {
        $this->assertIsString($url);
        $this->assertNotSame('', $url);
        $this->assertStringStartsWith('https://', $url);
        $this->assertStringContainsString($path, $url);
        $this->assertStringContainsString('X-Amz-Expires=', $url);
        $this->assertStringContainsString('X-Amz-Signature=', $url);
        $this->assertStringNotContainsString('/storage/'.$path, $url);
        $this->assertDoesNotMatchRegularExpression('#/api/v1/#', $url);
    }
}
