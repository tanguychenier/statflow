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

namespace App\Ingestion\Application\Dto;

/**
 * Aggregate outcome of an ingestion request. The single-event endpoint inspects
 * `allAccepted()` to choose 204 vs 422; the batch endpoint renders the full
 * `results` list as the OpenAPI BatchResultResponse when some events failed.
 */
final readonly class IngestionResult
{
    /**
     * @param list<EventResult> $results
     */
    public function __construct(
        public array $results,
    ) {
    }

    public function accepted(): int
    {
        return count(array_filter($this->results, static fn (EventResult $r): bool => $r->accepted));
    }

    public function rejected(): int
    {
        return count(array_filter($this->results, static fn (EventResult $r): bool => !$r->accepted));
    }

    public function allAccepted(): bool
    {
        return $this->rejected() === 0;
    }

    /**
     * @return EventResult|null the first (and, for the single endpoint, only) rejection
     */
    public function firstRejection(): ?EventResult
    {
        foreach ($this->results as $result) {
            if (!$result->accepted) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @return array{accepted: int, rejected: int, results: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'accepted' => $this->accepted(),
            'rejected' => $this->rejected(),
            'results' => array_map(static fn (EventResult $r): array => $r->toArray(), $this->results),
        ];
    }
}
