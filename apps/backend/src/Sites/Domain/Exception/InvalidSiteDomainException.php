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

final class InvalidSiteDomainException extends SitesDomainException
{
    public function getType(): string
    {
        return 'https://statflow.dev/errors/validation-failed';
    }

    public static function empty(): self
    {
        return new self('Site domain must not be empty.');
    }

    public static function tooLong(string $domain, int $max): self
    {
        return new self(sprintf('Site domain "%s" exceeds the maximum length of %d characters.', $domain, $max));
    }

    public static function malformed(string $domain): self
    {
        return new self(sprintf('"%s" is not a valid bare hostname (no scheme, port or path allowed).', $domain));
    }
}
