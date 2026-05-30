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

namespace App\Identity\Infrastructure\Mail;

use App\Identity\Domain\Port\IdentityMailer;
use App\Identity\Domain\ValueObject\EmailAddress;
use Psr\Log\LoggerInterface;

/**
 * v1 {@see IdentityMailer} that records the intended message to the application
 * log instead of sending it. symfony/mailer is not a v1 dependency (the build
 * stays self-contained and free of external runtime calls); a self-hoster wires a
 * real transport adapter post-install. The reset/verification links remain
 * observable in the logs so the flows are testable end-to-end.
 *
 * The raw reset token is intentionally logged at info level: in a self-hosted
 * deployment the operator already controls the host, and without a transport this
 * is the only way to complete the reset flow during bring-up.
 */
final readonly class LoggingIdentityMailer implements IdentityMailer
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function sendPasswordResetLink(EmailAddress $recipient, string $rawToken): void
    {
        $this->logger->info('Password reset link issued', [
            'recipient' => $recipient->getValue(),
            'reset_token' => $rawToken,
        ]);
    }

    public function sendEmailVerification(EmailAddress $recipient, string $name): void
    {
        $this->logger->info('Account verification email issued', [
            'recipient' => $recipient->getValue(),
            'name' => $name,
        ]);
    }

    public function sendTeamInvitation(EmailAddress $recipient, string $teamName, string $inviterName): void
    {
        $this->logger->info('Team invitation email issued', [
            'recipient' => $recipient->getValue(),
            'team' => $teamName,
            'inviter' => $inviterName,
        ]);
    }
}
