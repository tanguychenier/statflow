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

namespace App\Tests\Identity\Support;

use App\Identity\Domain\Port\IdentityMailer;
use App\Identity\Domain\ValueObject\EmailAddress;

/**
 * Records every dispatched message so tests can assert on the recipient and the
 * raw reset token without a transport.
 */
final class RecordingIdentityMailer implements IdentityMailer
{
    /**
     * @var list<array{recipient: string, token: string}>
     */
    public array $resetLinks = [];

    /**
     * @var list<array{recipient: string, name: string}>
     */
    public array $verifications = [];

    /**
     * @var list<array{recipient: string, team: string, inviter: string}>
     */
    public array $invitations = [];

    public function sendPasswordResetLink(EmailAddress $recipient, string $rawToken): void
    {
        $this->resetLinks[] = [
            'recipient' => $recipient->getValue(),
            'token' => $rawToken,
        ];
    }

    public function sendEmailVerification(EmailAddress $recipient, string $name): void
    {
        $this->verifications[] = [
            'recipient' => $recipient->getValue(),
            'name' => $name,
        ];
    }

    public function sendTeamInvitation(EmailAddress $recipient, string $teamName, string $inviterName): void
    {
        $this->invitations[] = [
            'recipient' => $recipient->getValue(),
            'team' => $teamName,
            'inviter' => $inviterName,
        ];
    }
}
