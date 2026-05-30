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

final class InvalidSegment extends DomainException
{
    public static function emptyName(): self
    {
        return new self('Segment name must not be empty.');
    }

    public static function nameTooLong(int $max): self
    {
        return new self(sprintf('Segment name must not exceed %d characters.', $max));
    }

    public static function noFilters(): self
    {
        return new self('A segment must define at least one filter.');
    }
}
