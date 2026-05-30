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

final class MissingQueryField extends DomainException
{
    public static function named(string $field): self
    {
        return new self(sprintf('Required field "%s" is missing or empty.', $field));
    }
}
