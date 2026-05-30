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

namespace App\Analytics\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\Exception\ErrorType;

final class InvalidDateRange extends DomainException
{
    public function errorType(): ErrorType
    {
        return ErrorType::InvalidDateRange;
    }

    public static function malformed(string $field, string $value): self
    {
        return new self(sprintf('"%s" is not a valid %s date (expected YYYY-MM-DD).', $value, $field));
    }

    public static function orderInverted(): self
    {
        return new self('date_from must not be after date_to.');
    }

    public static function tooLong(int $maxDays): self
    {
        return new self(sprintf('Date range exceeds the maximum of %d days.', $maxDays));
    }
}
