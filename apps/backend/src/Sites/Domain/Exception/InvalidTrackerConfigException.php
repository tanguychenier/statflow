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

final class InvalidTrackerConfigException extends SitesDomainException
{
    public function getType(): string
    {
        return 'https://statflow.dev/errors/validation-failed';
    }

    public static function tooManySelectors(int $max): self
    {
        return new self(sprintf('At most %d ignored selectors are allowed.', $max));
    }
}
