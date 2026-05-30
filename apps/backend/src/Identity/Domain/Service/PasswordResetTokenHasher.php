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

namespace App\Identity\Domain\Service;

/**
 * Deterministic SHA-256 digest of a password-reset token. The raw token is high
 * entropy, so a fast hash is sufficient (the same rationale ADR-0009 §4 gives for
 * SHA-256 on API keys) and lets a reset attempt look the token up by hash without
 * ever storing the usable value. 64-char hex output matches the token_hash column.
 */
final class PasswordResetTokenHasher
{
    public static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
