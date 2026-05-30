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

final class InvalidSamplingRateException extends SitesDomainException
{
    public function getType(): string
    {
        return 'https://statflow.dev/errors/validation-failed';
    }

    public static function outOfRange(float $value): self
    {
        return new self(sprintf('Sampling rate must be between 0.0 and 1.0, got %s.', rtrim(rtrim(sprintf('%.3f', $value), '0'), '.')));
    }
}
