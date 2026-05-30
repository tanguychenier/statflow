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

use App\Identity\Application\Command\LogoutCommand;
use App\Identity\Domain\Port\AuditLogger;
use App\Identity\Domain\Port\RefreshTokenStore;

/**
 * Ends a session by revoking the refresh token server-side (api/README.md §2.1).
 * Idempotent: logging out with a missing or already-revoked token still succeeds,
 * so the cookie can always be cleared.
 */
final readonly class LogoutHandler
{
    public function __construct(
        private RefreshTokenStore $refreshTokenStore,
        private AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(LogoutCommand $command): void
    {
        if ($command->refreshToken !== null && $command->refreshToken !== '') {
            $this->refreshTokenStore->revoke($command->refreshToken);
        }

        $this->auditLogger->record(
            action: 'user.logged_out',
            context: $command->auditContext,
            resourceType: 'user',
            resourceId: $command->auditContext->actorId?->getValue(),
        );
    }
}
