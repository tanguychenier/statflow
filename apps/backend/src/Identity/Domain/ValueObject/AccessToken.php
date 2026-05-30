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
 * A freshly issued JWT access token plus its lifetime, shaped for the
 * TokenResponse schema (openapi.yaml): { access_token, token_type, expires_in }.
 */
final readonly class AccessToken
{
    public function __construct(
        public string $jwt,
        public int $expiresInSeconds,
    ) {
    }
}
