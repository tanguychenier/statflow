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
 * The site exceeded its per-window ingestion rate limit
 * (error-catalog.md `rate-limit-exceeded`, HTTP 429).
 */
final class RateLimitExceeded extends IngestionException
{
    private function __construct(
        string $message,
        private readonly int $retryAfterSeconds,
    ) {
        parent::__construct($message);
    }

    public static function retryAfter(int $seconds): self
    {
        return new self(
            sprintf('Ingestion rate limit exceeded; retry after %d seconds.', $seconds),
            max(1, $seconds),
        );
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }

    public function slug(): string
    {
        return 'rate-limit-exceeded';
    }

    public function title(): string
    {
        return 'Rate Limit Exceeded';
    }

    public function status(): int
    {
        return 429;
    }
}
