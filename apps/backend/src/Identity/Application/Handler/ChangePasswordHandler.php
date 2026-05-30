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

use App\Identity\Application\Command\ChangePasswordCommand;
use App\Identity\Domain\Exception\InvalidCredentialsException;
use App\Identity\Domain\Exception\UserNotFoundException;
use App\Identity\Domain\Port\AuditLogger;
use App\Identity\Domain\Port\PasswordHasher;
use App\Identity\Domain\Port\RefreshTokenStore;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\PlainPassword;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Changes the current user's password (PUT /users/me/password). The current
 * password must be supplied and verified; on success every other session is
 * revoked (openapi.yaml notes all sessions are revoked).
 */
final readonly class ChangePasswordHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private RefreshTokenStore $refreshTokenStore,
        private AuditLogger $auditLogger,
        private Clock $clock,
    ) {
    }

    public function __invoke(ChangePasswordCommand $command): void
    {
        $userId = Uuid::fromString($command->userId);
        $newPassword = PlainPassword::fromString($command->newPassword);

        $user = $this->users->findById($userId);

        if ($user === null || $user->isDeleted()) {
            throw UserNotFoundException::withId($userId);
        }

        $currentHash = $user->passwordHash();
        $currentPassword = PlainPassword::forVerification($command->currentPassword);

        if ($currentHash === null || !$this->passwordHasher->verify($currentPassword, $currentHash)) {
            throw new InvalidCredentialsException();
        }

        $now = $this->clock->now();
        $user->changePassword($this->passwordHasher->hash($newPassword), $now);
        $this->users->save($user);

        $this->refreshTokenStore->revokeAllForUser($userId);

        $this->auditLogger->record(
            action: 'user.password_changed',
            context: $command->auditContext,
            resourceType: 'user',
            resourceId: $userId->getValue(),
        );
    }
}
