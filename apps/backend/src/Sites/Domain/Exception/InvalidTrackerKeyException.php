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

final class InvalidTrackerKeyException extends SitesDomainException
{
    public function getType(): string
    {
        return 'https://statflow.dev/errors/validation-failed';
    }

    public static function badPrefix(string $value): self
    {
        return new self(sprintf('Tracker key "%s" must start with the "stk_" prefix.', $value));
    }

    public static function badSuffix(string $value): self
    {
        return new self(sprintf('Tracker key "%s" has an invalid random suffix.', $value));
    }
}
