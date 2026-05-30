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

use RuntimeException;

/**
 * Base class for every failure mode of the ingestion pipeline.
 *
 * Carries the RFC 9457 `type` slug, human title, HTTP status, and an optional
 * machine-readable error `code` (the error-catalog slug) so the HTTP layer can
 * render a Problem Details body without a translation table.
 */
abstract class IngestionException extends RuntimeException
{
    private const TYPE_BASE = 'https://statflow.io/errors/';

    abstract public function slug(): string;

    abstract public function title(): string;

    abstract public function status(): int;

    public function type(): string
    {
        return self::TYPE_BASE . $this->slug();
    }
}
