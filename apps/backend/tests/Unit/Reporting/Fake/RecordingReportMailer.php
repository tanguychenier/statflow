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

namespace App\Tests\Unit\Reporting\Fake;

use App\Reporting\Domain\Port\ReportMailer;
use App\Reporting\Domain\ValueObject\EmailAddress;
use RuntimeException;

/**
 * Records sent messages so tests can assert (or suppress) delivery. The
 * configured flag and a per-recipient failure switch let tests exercise the
 * graceful-degradation paths.
 */
final class RecordingReportMailer implements ReportMailer
{
    /**
     * @var list<array{to: string, subject: string}>
     */
    public array $sent = [];

    public function __construct(
        private bool $configured = true,
        private bool $failOnSend = false,
    ) {
    }

    public function configured(bool $configured): self
    {
        $this->configured = $configured;

        return $this;
    }

    public function failing(bool $failing): self
    {
        $this->failOnSend = $failing;

        return $this;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function send(EmailAddress $to, string $subject, string $htmlBody, string $textBody, array $attachments = []): void
    {
        if ($this->failOnSend) {
            throw new RuntimeException('transport failure');
        }

        $this->sent[] = [
            'to' => $to->value(),
            'subject' => $subject,
        ];
    }
}
