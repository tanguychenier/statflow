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

namespace App\Identity\Domain\ValueObject;

/**
 * A newly minted opaque refresh token: the raw value handed to the client in the
 * sf_rt cookie, and the TTL the cookie should carry (30 days — api/README.md).
 * The server stores only a hash of the raw value (RefreshTokenStore adapter).
 */
final readonly class RefreshToken
{
    public function __construct(
        public string $value,
        public int $ttlSeconds,
    ) {
    }
}
