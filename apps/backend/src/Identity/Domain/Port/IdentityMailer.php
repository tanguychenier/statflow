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

namespace App\Identity\Domain\Port;

use App\Identity\Domain\ValueObject\EmailAddress;

/**
 * Outbound transactional email for identity flows. Keeping it behind a port lets
 * the domain trigger emails without coupling to a transport, and lets tests
 * assert on dispatched messages.
 */
interface IdentityMailer
{
    /**
     * Send a password-reset link. The raw token is embedded in the link; the
     * caller has already stored only its hash.
     */
    public function sendPasswordResetLink(EmailAddress $recipient, string $rawToken): void;

    /**
     * Send an account-verification email after registration.
     */
    public function sendEmailVerification(EmailAddress $recipient, string $name): void;

    /**
     * Notify an invitee that they have been added to a team.
     */
    public function sendTeamInvitation(EmailAddress $recipient, string $teamName, string $inviterName): void;
}
