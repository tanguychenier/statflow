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
 * Raised when a scheduled report's cron expression or timezone is invalid, or
 * the schedule would fire more often than once a day (error-catalog
 * `validation-failed`, HTTP 422).
 */
final class InvalidScheduleException extends ReportingDomainException
{
    public static function malformedCron(string $value): self
    {
        return new self(sprintf('"%s" is not a valid 5-field cron expression.', $value));
    }

    public static function subDailyCron(string $value): self
    {
        return new self(sprintf('Schedule "%s" must fire at most once per day.', $value));
    }

    public static function unsatisfiableCron(string $value): self
    {
        return new self(sprintf('Schedule "%s" never matches a valid date.', $value));
    }

    public static function unknownTimezone(string $value): self
    {
        return new self(sprintf('"%s" is not a known IANA timezone.', $value));
    }

    public static function cronAndTimezoneTogether(): self
    {
        return new self('Schedule cron and timezone must be provided together.');
    }
}
