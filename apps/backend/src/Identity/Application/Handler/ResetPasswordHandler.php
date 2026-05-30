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

use App\Identity\Application\Command\ResetPasswordCommand;
use App\Identity\Domain\Exception\InvalidResetTokenException;
use App\Identity\Domain\Exception\UserNotFoundException;
use App\Identity\Domain\Port\AuditLogger;
use App\Identity\Domain\Port\PasswordHasher;
use App\Identity\Domain\Port\PasswordResetTokenRepository;
use App\Identity\Domain\Port\RefreshTokenStore;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\Service\PasswordResetTokenHasher;
use App\Identity\Domain\ValueObject\PlainPassword;
use App\Shared\Domain\Clock\Clock;

/**
 * Completes a password reset: verifies the single-use token, sets the new
 * (policy-checked) password, consumes the token, and revokes every existing
 * session so a stolen old session cannot survive the reset (openapi.yaml
 * /auth/reset-password).
 */
final readonly class ResetPasswordHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordResetTokenRepository $resetTokens,
        private PasswordHasher $passwordHasher,
        private RefreshTokenStore $refreshTokenStore,
        private AuditLogger $auditLogger,
        private Clock $clock,
    ) {
    }

    public function __invoke(ResetPasswordCommand $command): void
    {
        $newPassword = PlainPassword::fromString($command->newPassword);

        $token = $this->resetTokens->findByTokenHash(PasswordResetTokenHasher::hash($command->token));
        $now = $this->clock->now();

        if ($token === null || !$token->isUsable($now)) {
            throw new InvalidResetTokenException();
        }

        $user = $this->users->findById($token->userId());

        if ($user === null || $user->isDeleted()) {
            throw UserNotFoundException::withId($token->userId());
        }

        $user->changePassword($this->passwordHasher->hash($newPassword), $now);
        $this->users->save($user);

        $token->consume($now);
        $this->resetTokens->save($token);
        $this->resetTokens->invalidateAllForUser($user->id());

        $this->refreshTokenStore->revokeAllForUser($user->id());

        $this->auditLogger->record(
            action: 'user.password_reset',
            context: $command->auditContext,
            resourceType: 'user',
            resourceId: $user->id()->getValue(),
        );
    }
}
