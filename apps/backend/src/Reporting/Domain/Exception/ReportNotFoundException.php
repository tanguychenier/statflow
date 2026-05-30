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

use App\Shared\Domain\Exception\ErrorType;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Raised when a saved report, scheduled report, alert or export cannot be found
 * for the resolved site (error-catalog `not-found`, HTTP 404). A resource that
 * exists under a different site is reported the same way to avoid disclosure.
 */
final class ReportNotFoundException extends ReportingDomainException
{
    public function errorType(): ErrorType
    {
        return ErrorType::NotFound;
    }

    public static function savedReport(Uuid $id): self
    {
        return new self(sprintf('Saved report "%s" does not exist.', $id->getValue()));
    }

    public static function scheduledReport(Uuid $id): self
    {
        return new self(sprintf('Scheduled report "%s" does not exist.', $id->getValue()));
    }

    public static function alert(Uuid $id): self
    {
        return new self(sprintf('Alert "%s" does not exist.', $id->getValue()));
    }

    public static function export(Uuid $id): self
    {
        return new self(sprintf('Export "%s" does not exist.', $id->getValue()));
    }
}
