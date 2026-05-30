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

namespace App\Reporting\Infrastructure\Mailer;

use App\Reporting\Domain\Port\ReportMailer;
use App\Reporting\Domain\ValueObject\EmailAddress;

/**
 * Default, dependency-free {@see ReportMailer} for installs without email.
 *
 * Email is opt-in and self-hosted: a minimal deployment ships no SMTP transport,
 * so this adapter reports itself unconfigured and the scheduled-report, alert and
 * export-notification use cases skip sending while still advancing their state.
 * When an operator enables SMTP, the wiring agent swaps in a transport-backed
 * adapter (a thin wrapper over Symfony Mailer) that satisfies the same contract.
 */
final class NullReportMailer implements ReportMailer
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function send(
        EmailAddress $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $attachments = [],
    ): void {
        // Intentionally a no-op: an unconfigured install never delivers mail.
    }
}
