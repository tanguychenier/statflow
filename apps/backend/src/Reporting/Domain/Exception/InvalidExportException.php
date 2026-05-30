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

namespace App\Reporting\Domain\Exception;

/**
 * Raised when an export request is invalid (unknown format) or an export state
 * transition is illegal (error-catalog `validation-failed`, HTTP 422).
 */
final class InvalidExportException extends ReportingDomainException
{
    public static function unknownFormat(string $value): self
    {
        return new self(sprintf('Unsupported export format "%s".', $value));
    }

    public static function illegalTransition(string $from, string $to): self
    {
        return new self(sprintf('An export cannot transition from "%s" to "%s".', $from, $to));
    }
}
