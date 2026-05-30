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

use App\Identity\Domain\Port\RefreshTokenStore;
use App\Identity\Domain\ValueObject\RefreshToken;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * In-memory refresh-token store reproducing the production semantics: single-use
 * rotation and logout-everywhere revocation.
 */
final class InMemoryRefreshTokenStore implements RefreshTokenStore
{
    /**
     * @var array<string, string> raw token => user id
     */
    private array $tokens = [];

    private int $counter = 0;

    public function issue(Uuid $userId): RefreshToken
    {
        $raw = 'rt-' . (++$this->counter) . '-' . $userId->getValue();
        $this->tokens[$raw] = $userId->getValue();

        return new RefreshToken($raw, 2_592_000);
    }

    public function resolveUserId(string $rawToken): ?Uuid
    {
        $userId = $this->tokens[$rawToken] ?? null;

        return $userId !== null ? Uuid::fromString($userId) : null;
    }

    public function rotate(string $rawToken): ?RefreshToken
    {
        $userId = $this->resolveUserId($rawToken);

        if ($userId === null) {
            return null;
        }

        $this->revoke($rawToken);

        return $this->issue($userId);
    }

    public function revoke(string $rawToken): void
    {
        unset($this->tokens[$rawToken]);
    }

    public function revokeAllForUser(Uuid $userId): void
    {
        foreach ($this->tokens as $raw => $owner) {
            if ($owner === $userId->getValue()) {
                unset($this->tokens[$raw]);
            }
        }
    }

    public function count(): int
    {
        return count($this->tokens);
    }
}
