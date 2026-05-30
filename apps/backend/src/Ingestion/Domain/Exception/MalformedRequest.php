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
 * The request body is not the JSON shape the endpoint expects: it is not valid
 * JSON, or it is neither a single event object nor a batch envelope
 * (error-catalog.md `malformed-json`, HTTP 400).
 */
final class MalformedRequest extends IngestionException
{
    public static function notJson(): self
    {
        return new self('Request body is not valid JSON.');
    }

    public static function notAnObject(): self
    {
        return new self('Request body must be a JSON object.');
    }

    public static function emptyBatch(): self
    {
        return new self('Batch envelope must contain at least one event.');
    }

    public static function reason(string $reason): self
    {
        return new self($reason);
    }

    public function slug(): string
    {
        return 'malformed-json';
    }

    public function title(): string
    {
        return 'Malformed JSON';
    }

    public function status(): int
    {
        return 400;
    }
}
