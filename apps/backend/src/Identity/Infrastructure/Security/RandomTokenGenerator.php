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

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Port\TokenGenerator;

/**
 * Produces URL-safe, high-entropy random tokens for password-reset links and
 * API-key secrets, sourced from the CSPRNG. Base64url avoids characters that
 * would need escaping in a URL or a Bearer header.
 */
final class RandomTokenGenerator implements TokenGenerator
{
    public function generate(int $byteLength = 32): string
    {
        $bytes = random_bytes(max(1, $byteLength));

        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
