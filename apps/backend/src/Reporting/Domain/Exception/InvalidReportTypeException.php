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
 * Raised when a saved report's `report_type` is not one of the supported query
 * families (error-catalog `validation-failed`, HTTP 422).
 */
final class InvalidReportTypeException extends ReportingDomainException
{
    public static function unknown(string $value): self
    {
        return new self(sprintf('Unsupported report type "%s".', $value));
    }
}
