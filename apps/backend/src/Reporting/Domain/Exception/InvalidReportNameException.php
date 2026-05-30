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
 * Raised when a report/alert name or description violates its length bounds
 * (error-catalog `validation-failed`, HTTP 422).
 */
final class InvalidReportNameException extends ReportingDomainException
{
    public static function empty(): self
    {
        return new self('Report name must not be empty.');
    }

    public static function tooLong(int $max): self
    {
        return new self(sprintf('Report name must not exceed %d characters.', $max));
    }

    public static function descriptionTooLong(int $max): self
    {
        return new self(sprintf('Report description must not exceed %d characters.', $max));
    }
}
