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
 * Raised when a saved-report or export query definition is not a JSON object,
 * cannot be serialised, or exceeds its size bound (error-catalog
 * `validation-failed`, HTTP 422).
 */
final class InvalidReportDefinitionException extends ReportingDomainException
{
    public static function notAnObject(): self
    {
        return new self('Report query must be a JSON object.');
    }

    public static function notSerialisable(): self
    {
        return new self('Report query could not be serialised.');
    }

    public static function tooLarge(int $maxBytes): self
    {
        return new self(sprintf('Report query must not exceed %d bytes when serialised.', $maxBytes));
    }
}
