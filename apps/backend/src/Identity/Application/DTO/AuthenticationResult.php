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

namespace App\Identity\Application\DTO;

use App\Identity\Domain\ValueObject\AccessToken;
use App\Identity\Domain\ValueObject\RefreshToken;

/**
 * The outcome of a successful login or refresh: the access token for the
 * response body and the rotating refresh token for the sf_rt cookie. The HTTP
 * layer turns this into a TokenResponse plus a Set-Cookie header.
 */
final readonly class AuthenticationResult
{
    public function __construct(
        public AccessToken $accessToken,
        public RefreshToken $refreshToken,
    ) {
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int}
     */
    public function toTokenResponse(): array
    {
        return [
            'access_token' => $this->accessToken->jwt,
            'token_type' => 'Bearer',
            'expires_in' => $this->accessToken->expiresInSeconds,
        ];
    }
}
