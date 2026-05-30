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

use App\Identity\Domain\Model\PasswordResetToken;
use App\Shared\Domain\ValueObject\Uuid;

interface PasswordResetTokenRepository
{
    public function save(PasswordResetToken $token): void;

    public function findByTokenHash(string $tokenHash): ?PasswordResetToken;

    /**
     * Invalidate all outstanding (unconsumed) tokens for a user. Called when a
     * new reset is requested or after a successful password change.
     */
    public function invalidateAllForUser(Uuid $userId): void;
}
