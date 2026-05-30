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

namespace App\Tests\Unit\Ingestion\Infrastructure;

use App\Ingestion\Infrastructure\Salt\HkdfSaltProvider;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HkdfSaltProvider::class)]
final class HkdfSaltProviderTest extends TestCase
{
    private const SECRET = 'install-secret-with-enough-entropy';

    #[Test]
    public function itDerivesA256BitSalt(): void
    {
        $salt = (new HkdfSaltProvider(self::SECRET))->saltForDate('2025-06-15');

        self::assertSame(32, strlen($salt));
    }

    #[Test]
    public function itMatchesTheAdr0008Hkdf(): void
    {
        $expected = hash_hkdf('sha256', self::SECRET, 32, 'statflow-daily-salt', '2025-06-15');

        self::assertSame($expected, (new HkdfSaltProvider(self::SECRET))->saltForDate('2025-06-15'));
    }

    #[Test]
    public function itIsDeterministicForADate(): void
    {
        $provider = new HkdfSaltProvider(self::SECRET);

        self::assertSame($provider->saltForDate('2025-06-15'), $provider->saltForDate('2025-06-15'));
    }

    #[Test]
    public function itRotatesAcrossDates(): void
    {
        $provider = new HkdfSaltProvider(self::SECRET);

        self::assertNotSame($provider->saltForDate('2025-06-15'), $provider->saltForDate('2025-06-16'));
    }

    #[Test]
    public function differentInstallSecretsProduceDifferentSalts(): void
    {
        $a = (new HkdfSaltProvider('secret-a'))->saltForDate('2025-06-15');
        $b = (new HkdfSaltProvider('secret-b'))->saltForDate('2025-06-15');

        self::assertNotSame($a, $b);
    }

    #[Test]
    public function itRejectsAnEmptyInstallSecret(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HkdfSaltProvider('');
    }
}
