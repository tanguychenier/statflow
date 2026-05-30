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

namespace App\Identity\Application\Handler;

use App\Identity\Application\Command\RefreshSessionCommand;
use App\Identity\Application\DTO\AuthenticationResult;
use App\Identity\Application\Service\TeamClaimsAssembler;
use App\Identity\Domain\Exception\InvalidRefreshTokenException;
use App\Identity\Domain\Port\AccessTokenIssuer;
use App\Identity\Domain\Port\RefreshTokenStore;
use App\Identity\Domain\Port\UserRepository;

/**
 * Rotates the refresh token and issues a fresh access token. The presented token
 * is resolved to its user, then atomically rotated (old token revoked, new one
 * minted) to defeat token replay. A missing, unknown, expired, or already-rotated
 * token fails uniformly (error catalog: token-revoked).
 */
final readonly class RefreshSessionHandler
{
    public function __construct(
        private RefreshTokenStore $refreshTokenStore,
        private AccessTokenIssuer $accessTokenIssuer,
        private TeamClaimsAssembler $claimsAssembler,
        private UserRepository $users,
    ) {
    }

    public function __invoke(RefreshSessionCommand $command): AuthenticationResult
    {
        $rawToken = $command->refreshToken;

        if ($rawToken === null || $rawToken === '') {
            throw new InvalidRefreshTokenException();
        }

        $userId = $this->refreshTokenStore->resolveUserId($rawToken);

        if ($userId === null) {
            throw new InvalidRefreshTokenException();
        }

        $user = $this->users->findById($userId);

        if ($user === null || $user->isDeleted()) {
            $this->refreshTokenStore->revoke($rawToken);

            throw new InvalidRefreshTokenException();
        }

        $rotated = $this->refreshTokenStore->rotate($rawToken);

        if ($rotated === null) {
            throw new InvalidRefreshTokenException();
        }

        $claims = $this->claimsAssembler->forUser($userId);
        $accessToken = $this->accessTokenIssuer->issue($userId, $claims);

        return new AuthenticationResult($accessToken, $rotated);
    }
}
