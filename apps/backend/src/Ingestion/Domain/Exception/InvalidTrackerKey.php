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

namespace App\Ingestion\Domain\Exception;

/**
 * The `site_key` is malformed, unknown, or belongs to a disabled site
 * (error-catalog.md `invalid-tracker-key`, HTTP 401).
 */
final class InvalidTrackerKey extends IngestionException
{
    public static function malformed(): self
    {
        return new self('The site_key is not a well-formed tracker key.');
    }

    public static function unknown(): self
    {
        return new self('The site_key does not match any registered, enabled site.');
    }

    public function slug(): string
    {
        return 'invalid-tracker-key';
    }

    public function title(): string
    {
        return 'Invalid Tracker Key';
    }

    public function status(): int
    {
        return 401;
    }
}
