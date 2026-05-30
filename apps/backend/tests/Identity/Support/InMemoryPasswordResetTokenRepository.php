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

namespace App\Tests\Identity\Support;

use App\Identity\Domain\Model\PasswordResetToken;
use App\Identity\Domain\Port\PasswordResetTokenRepository;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class InMemoryPasswordResetTokenRepository implements PasswordResetTokenRepository
{
    /**
     * @var array<string, PasswordResetToken>
     */
    private array $tokens = [];

    public function save(PasswordResetToken $token): void
    {
        $this->tokens[$token->id()->getValue()] = $token;
    }

    public function findByTokenHash(string $tokenHash): ?PasswordResetToken
    {
        foreach ($this->tokens as $token) {
            if (hash_equals($token->tokenHash(), $tokenHash)) {
                return $token;
            }
        }

        return null;
    }

    public function invalidateAllForUser(Uuid $userId): void
    {
        $now = new DateTimeImmutable();
        foreach ($this->tokens as $token) {
            if ($token->userId()->equals($userId) && !$token->isConsumed()) {
                $token->consume($now);
            }
        }
    }
}
