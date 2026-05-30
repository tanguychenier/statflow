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

namespace App\Reporting\Domain\Port;

use App\Reporting\Domain\ValueObject\EmailAddress;

/**
 * Driven port for outbound email (scheduled reports, alert notifications,
 * export-ready notices).
 *
 * Email delivery is optional and self-hosted: when no SMTP transport is
 * configured the adapter reports {@see isConfigured()} as false and the use
 * cases skip sending rather than failing. This keeps a minimal install fully
 * functional without an email server.
 */
interface ReportMailer
{
    /**
     * Whether a working transport is configured. Use cases consult this before
     * attempting a send so an unconfigured install degrades gracefully.
     */
    public function isConfigured(): bool;

    /**
     * Deliver a message to a single recipient. Implementations must not throw on
     * a transient transport error during a batch send; they should report
     * failure so the caller can decide whether to retry.
     *
     * @param list<array{filename: string, contentType: string, contents: string}> $attachments
     */
    public function send(
        EmailAddress $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $attachments = [],
    ): void;
}
