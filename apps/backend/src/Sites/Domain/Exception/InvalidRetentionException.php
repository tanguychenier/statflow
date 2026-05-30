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

namespace App\Sites\Domain\Exception;

final class InvalidRetentionException extends SitesDomainException
{
    public function getType(): string
    {
        return 'https://statflow.dev/errors/validation-failed';
    }

    public static function outOfRange(int $value, int $min, int $max): self
    {
        return new self(sprintf('Data retention must be between %d and %d days, got %d.', $min, $max, $value));
    }
}
