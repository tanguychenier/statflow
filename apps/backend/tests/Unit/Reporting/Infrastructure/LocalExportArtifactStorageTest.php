<?php

declare(strict_types=1);

/*
 * This file is part of Statflow.
 *
 * (c) Tanguy Chénier <tanguychenier@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Unit\Reporting\Infrastructure;

use App\Reporting\Infrastructure\Storage\LocalExportArtifactStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalExportArtifactStorage::class)]
final class LocalExportArtifactStorageTest extends TestCase
{
    private string $dir;

    private LocalExportArtifactStorage $storage;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/statflow-exports-' . bin2hex(random_bytes(6));
        $this->storage = new LocalExportArtifactStorage($this->dir, 'top-secret');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            $files = glob($this->dir . '/*');
            foreach ($files !== false ? $files : [] as $file) {
                @unlink($file);
            }
            @rmdir($this->dir);
        }
    }

    #[Test]
    public function itStoresAndReadsAnArtifact(): void
    {
        $key = $this->storage->store('export-1', 'csv', 'a,b\r\n1,2\r\n');

        self::assertSame('export-1.csv', $key);
        self::assertSame('a,b\r\n1,2\r\n', $this->storage->read($key));
    }

    #[Test]
    public function itMintsAndVerifiesSignedUrl(): void
    {
        $key = $this->storage->store('export-2', 'csv', 'data');
        $expiry = time() + 3600;

        $url = $this->storage->downloadUrl($key, $expiry);

        self::assertNotNull($url);
        self::assertStringContainsString('key=export-2.csv', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        /** @var array<string, string> $params */
        self::assertTrue($this->storage->verify($params['key'], (int) $params['exp'], $params['sig']));
    }

    #[Test]
    public function itRejectsTamperedSignature(): void
    {
        $key = $this->storage->store('export-3', 'csv', 'data');
        $expiry = time() + 3600;

        self::assertFalse($this->storage->verify($key, $expiry, 'forged-signature'));
    }

    #[Test]
    public function itRejectsExpiredLink(): void
    {
        $key = $this->storage->store('export-4', 'csv', 'data');

        self::assertNull($this->storage->downloadUrl($key, time() - 1));
        self::assertFalse($this->storage->verify($key, time() - 1, 'whatever'));
    }

    #[Test]
    public function downloadUrlIsNullForMissingArtifact(): void
    {
        self::assertNull($this->storage->downloadUrl('does-not-exist.csv', time() + 3600));
        self::assertNull($this->storage->read('does-not-exist.csv'));
    }

    #[Test]
    public function itSanitisesTraversalAttempts(): void
    {
        $key = $this->storage->store('../../etc/passwd', 'csv', 'data');

        self::assertStringNotContainsString('/', $key);
        // The artifact lands inside the base directory, never above it.
        self::assertFileExists($this->dir . '/' . $key);
        self::assertFileDoesNotExist('/etc/passwd.csv');
    }
}
