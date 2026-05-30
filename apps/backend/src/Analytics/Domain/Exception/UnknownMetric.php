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

final class UnknownMetric extends DomainException
{
    public static function forName(string $name): self
    {
        return new self(sprintf('"%s" is not a known metric.', $name));
    }
}
