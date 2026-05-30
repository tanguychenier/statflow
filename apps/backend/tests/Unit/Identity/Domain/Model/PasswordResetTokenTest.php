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

use App\Identity\Domain\Model\PasswordResetToken;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordResetToken::class)]
final class PasswordResetTokenTest extends TestCase
{
    #[Test]
    public function aFreshTokenIsUsable(): void
    {
        $now = new DateTimeImmutable('2026-05-29T10:00:00+00:00');
        $token = $this->token($now->modify('+1 hour'), $now);

        self::assertTrue($token->isUsable($now));
        self::assertFalse($token->isConsumed());
        self::assertFalse($token->isExpired($now));
    }

    #[Test]
    public function anExpiredTokenIsNotUsable(): void
    {
        $issuedAt = new DateTimeImmutable('2026-05-29T08:00:00+00:00');
        $token = $this->token(new DateTimeImmutable('2026-05-29T09:00:00+00:00'), $issuedAt);

        $checkAt = new DateTimeImmutable('2026-05-29T10:00:00+00:00');

        self::assertTrue($token->isExpired($checkAt));
        self::assertFalse($token->isUsable($checkAt));
    }

    #[Test]
    public function consumingMakesItUnusableAndIsIdempotent(): void
    {
        $now = new DateTimeImmutable('2026-05-29T10:00:00+00:00');
        $token = $this->token($now->modify('+1 hour'), $now);

        $token->consume($now);
        $consumedAt = $token->consumedAt();
        $token->consume(new DateTimeImmutable('2026-05-29T10:30:00+00:00'));

        self::assertTrue($token->isConsumed());
        self::assertFalse($token->isUsable($now));
        self::assertEquals($consumedAt, $token->consumedAt());
    }

    private function token(DateTimeImmutable $expiresAt, DateTimeImmutable $now): PasswordResetToken
    {
        return new PasswordResetToken(
            Uuid::generate(),
            Uuid::generate(),
            hash('sha256', 'raw-token'),
            $expiresAt,
            $now,
        );
    }
}
