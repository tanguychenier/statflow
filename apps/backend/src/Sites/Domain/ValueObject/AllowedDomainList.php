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

namespace App\Sites\Domain\ValueObject;

use App\Sites\Domain\Exception\InvalidAllowedDomainException;

/**
 * Origins permitted to submit events for a site, checked by ingestion against
 * the request `Origin` header (ADR-0009).
 *
 * Entries are bare hostnames, optionally with a single leading `*.` wildcard
 * label (e.g. `*.example.com`). An empty list means "allow all origins" — valid
 * but discouraged in production, matching the schema default of `'{}'`.
 * Capped at 50 to match the API contract.
 */
final readonly class AllowedDomainList
{
    public const MAX_ENTRIES = 50;

    private const HOST_PATTERN = '/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/';

    /**
     * @param list<string> $entries
     */
    private function __construct(
        private array $entries
    ) {
    }

    /**
     * @param list<string> $raw
     */
    public static function fromArray(array $raw): self
    {
        $clean = [];

        foreach ($raw as $entry) {
            $entry = strtolower(trim($entry));

            if ($entry === '') {
                continue;
            }

            if (!self::isValidPattern($entry)) {
                throw InvalidAllowedDomainException::malformed($entry);
            }

            $clean[$entry] = $entry;
        }

        $clean = array_values($clean);

        if (count($clean) > self::MAX_ENTRIES) {
            throw InvalidAllowedDomainException::tooMany(self::MAX_ENTRIES);
        }

        return new self($clean);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @return list<string>
     */
    public function toArray(): array
    {
        return $this->entries;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    private static function isValidPattern(string $entry): bool
    {
        if ($entry === 'localhost') {
            return true;
        }

        $candidate = str_starts_with($entry, '*.') ? substr($entry, 2) : $entry;

        return preg_match(self::HOST_PATTERN, $candidate) === 1;
    }
}
