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
 * The request body exceeded the per-event or per-batch size budget
 * (error-catalog.md `event-payload-too-large`, HTTP 413).
 */
final class PayloadTooLarge extends IngestionException
{
    public static function single(int $limitBytes): self
    {
        return new self(sprintf('Single-event payload exceeds the %d-byte limit.', $limitBytes));
    }

    public static function batch(int $limitBytes): self
    {
        return new self(sprintf('Batch payload exceeds the %d-byte limit.', $limitBytes));
    }

    public static function tooManyEvents(int $limit): self
    {
        return new self(sprintf('Batch exceeds the %d-event limit.', $limit));
    }

    public function slug(): string
    {
        return 'event-payload-too-large';
    }

    public function title(): string
    {
        return 'Event Payload Too Large';
    }

    public function status(): int
    {
        return 413;
    }
}
