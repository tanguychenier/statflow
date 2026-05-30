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

final class SegmentNotFound extends DomainException
{
    public function errorType(): ErrorType
    {
        return ErrorType::NotFound;
    }

    public static function withId(string $id): self
    {
        return new self(sprintf('Segment "%s" does not exist for this site.', $id));
    }
}
