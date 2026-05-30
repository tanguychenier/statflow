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

namespace App\Tests\Unit\Identity\Domain\Model;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\ValueObject\EmailAddress;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    private const NOW = '2026-05-29T10:00:00+00:00';

    #[Test]
    public function itRegistersAnUnverifiedUserWithAHash(): void
    {
        $user = $this->register();

        self::assertSame('alice@example.com', $user->email()->getValue());
        self::assertSame('Alice', $user->name());
        self::assertFalse($user->isEmailVerified());
        self::assertFalse($user->isDeleted());
        $hash = $user->passwordHash();
        self::assertNotNull($hash);
        self::assertSame('hash', $hash->getValue());
    }

    #[Test]
    public function changingEmailResetsVerificationAndTouchesUpdatedAt(): void
    {
        $user = $this->register();
        $user->verifyEmail(new DateTimeImmutable(self::NOW));
        self::assertTrue($user->isEmailVerified());

        $later = new DateTimeImmutable('2026-05-30T10:00:00+00:00');
        $user->changeEmail(EmailAddress::fromString('new@example.com'), $later);

        self::assertSame('new@example.com', $user->email()->getValue());
        self::assertFalse($user->isEmailVerified());
        self::assertEquals($later, $user->updatedAt());
    }

    #[Test]
    public function changingToTheSameEmailIsANoOp(): void
    {
        $user = $this->register();
        $user->verifyEmail(new DateTimeImmutable(self::NOW));

        $user->changeEmail(EmailAddress::fromString('alice@example.com'), new DateTimeImmutable('2026-06-01T00:00:00+00:00'));

        self::assertTrue($user->isEmailVerified());
    }

    #[Test]
    public function itChangesThePasswordHash(): void
    {
        $user = $this->register();

        $user->changePassword(HashedPassword::fromHash('new-hash'), new DateTimeImmutable(self::NOW));

        self::assertSame('new-hash', $user->passwordHash()?->getValue());
    }

    #[Test]
    public function itRecordsLogin(): void
    {
        $user = $this->register();
        $loginAt = new DateTimeImmutable('2026-05-29T12:00:00+00:00');

        $user->recordLogin($loginAt);

        self::assertEquals($loginAt, $user->lastLoginAt());
    }

    #[Test]
    public function softDeleteIsIdempotent(): void
    {
        $user = $this->register();
        $first = new DateTimeImmutable(self::NOW);

        $user->softDelete($first);
        $user->softDelete(new DateTimeImmutable('2026-06-01T00:00:00+00:00'));

        self::assertTrue($user->isDeleted());
        self::assertEquals($first, $user->deletedAt());
    }

    private function register(): User
    {
        return User::register(
            Uuid::generate(),
            EmailAddress::fromString('alice@example.com'),
            'Alice',
            HashedPassword::fromHash('hash'),
            new DateTimeImmutable(self::NOW),
        );
    }
}
