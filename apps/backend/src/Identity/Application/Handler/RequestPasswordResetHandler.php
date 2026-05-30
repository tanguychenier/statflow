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

use App\Identity\Application\Command\RequestPasswordResetCommand;
use App\Identity\Domain\Model\PasswordResetToken;
use App\Identity\Domain\Port\IdentityMailer;
use App\Identity\Domain\Port\PasswordResetTokenRepository;
use App\Identity\Domain\Port\TokenGenerator;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\Service\PasswordResetTokenHasher;
use App\Identity\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Issues a single-use, 1-hour password-reset token (ADR-0009 §2) and emails the
 * link. The handler is intentionally branch-uniform: whether or not the address
 * is registered, it returns silently so callers can always answer 202 and no
 * timing/observable difference reveals account existence. Only the token hash is
 * stored; previous outstanding tokens for the user are invalidated first.
 */
final readonly class RequestPasswordResetHandler
{
    private const TTL_SECONDS = 3600;

    public function __construct(
        private UserRepository $users,
        private PasswordResetTokenRepository $resetTokens,
        private TokenGenerator $tokenGenerator,
        private IdentityMailer $mailer,
        private Clock $clock,
    ) {
    }

    public function __invoke(RequestPasswordResetCommand $command): void
    {
        $email = EmailAddress::fromString($command->email);
        $user = $this->users->findByEmail($email);

        if ($user === null || $user->isDeleted()) {
            return;
        }

        $this->resetTokens->invalidateAllForUser($user->id());

        $rawToken = $this->tokenGenerator->generate(32);
        $now = $this->clock->now();
        $token = new PasswordResetToken(
            Uuid::generate(),
            $user->id(),
            PasswordResetTokenHasher::hash($rawToken),
            $now->modify(sprintf('+%d seconds', self::TTL_SECONDS)),
            $now,
        );
        $this->resetTokens->save($token);

        $this->mailer->sendPasswordResetLink($email, $rawToken);
    }
}
