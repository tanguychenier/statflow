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

final class InvalidSiteSettingsException extends SitesDomainException
{
    public function getType(): string
    {
        return 'https://statflow.dev/errors/validation-failed';
    }

    public static function notArray(string $field): self
    {
        return new self(sprintf('Field "%s" must be an array.', $field));
    }

    public static function notStringList(string $field): self
    {
        return new self(sprintf('Field "%s" must be a list of strings.', $field));
    }

    public static function notBool(string $field): self
    {
        return new self(sprintf('Field "%s" must be a boolean.', $field));
    }

    public static function notInteger(string $field): self
    {
        return new self(sprintf('Field "%s" must be an integer.', $field));
    }

    public static function notNumber(string $field): self
    {
        return new self(sprintf('Field "%s" must be a number.', $field));
    }

    public static function notString(string $field): self
    {
        return new self(sprintf('Field "%s" must be a string.', $field));
    }
}
