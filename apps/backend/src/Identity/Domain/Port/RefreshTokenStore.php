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

namespace App\Identity\Domain\Port;

use App\Identity\Domain\ValueObject\RefreshToken;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Server-side store for opaque refresh tokens (Redis in production —
 * api/README.md §2.1). Tokens are single-use: refreshing rotates them, and
 * logout / password change revoke them. The raw value is never persisted, only
 * a hash, so a store leak cannot be replayed as a session.
 */
interface RefreshTokenStore
{
    /**
     * Mint, persist, and return a new refresh token bound to the user.
     */
    public function issue(Uuid $userId): RefreshToken;

    /**
     * Resolve the user a valid, non-expired token belongs to, or null if the
     * token is unknown, expired, or already rotated/revoked.
     */
    public function resolveUserId(string $rawToken): ?Uuid;

    /**
     * Atomically revoke the presented token and issue its replacement (rotation
     * on refresh). Returns null if the presented token is not valid.
     */
    public function rotate(string $rawToken): ?RefreshToken;

    public function revoke(string $rawToken): void;

    /**
     * Revoke every outstanding refresh token for a user (logout-everywhere on
     * password change / reset).
     */
    public function revokeAllForUser(Uuid $userId): void;
}
