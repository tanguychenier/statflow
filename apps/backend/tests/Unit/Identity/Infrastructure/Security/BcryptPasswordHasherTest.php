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

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Domain\ValueObject\PlainPassword;
use App\Identity\Infrastructure\Security\BcryptPasswordHasher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;

#[CoversClass(BcryptPasswordHasher::class)]
final class BcryptPasswordHasherTest extends TestCase
{
    private BcryptPasswordHasher $hasher;

    protected function setUp(): void
    {
        // Lowest bcrypt cost keeps the test fast; the algorithm is what matters.
        $this->hasher = new BcryptPasswordHasher(new NativePasswordHasher(null, null, 4, PASSWORD_BCRYPT));
    }

    #[Test]
    public function aHashedPasswordVerifies(): void
    {
        $hash = $this->hasher->hash(PlainPassword::fromString('correcthorse12'));

        self::assertStringStartsWith('$2', $hash->getValue());
        self::assertTrue($this->hasher->verify(PlainPassword::fromString('correcthorse12'), $hash));
    }

    #[Test]
    public function aWrongPasswordDoesNotVerify(): void
    {
        $hash = $this->hasher->hash(PlainPassword::fromString('correcthorse12'));

        self::assertFalse($this->hasher->verify(PlainPassword::fromString('wrongpassword1'), $hash));
    }

    #[Test]
    public function aFreshHashDoesNotNeedRehashing(): void
    {
        $hash = $this->hasher->hash(PlainPassword::fromString('correcthorse12'));

        self::assertFalse($this->hasher->needsRehash($hash));
    }
}
