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

use App\Sites\Domain\Exception\InvalidExcludedIpException;

/**
 * An allow/deny list of IPs and CIDR ranges excluded from tracking.
 *
 * Each entry is a single IPv4/IPv6 address or a CIDR block. Entries are
 * normalised (trimmed, de-duplicated, order preserved) and capped at 100 to
 * match the API contract. The PostgreSQL column is INET[], so only values that
 * survive this validation are ever persisted.
 */
final readonly class ExcludedIpList
{
    public const MAX_ENTRIES = 100;

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
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            if (!self::isValidIpOrCidr($entry)) {
                throw InvalidExcludedIpException::malformed($entry);
            }

            $clean[$entry] = $entry;
        }

        $clean = array_values($clean);

        if (count($clean) > self::MAX_ENTRIES) {
            throw InvalidExcludedIpException::tooMany(self::MAX_ENTRIES);
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

    public function contains(string $value): bool
    {
        return in_array($value, $this->entries, true);
    }

    private static function isValidIpOrCidr(string $entry): bool
    {
        if (filter_var($entry, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if (!str_contains($entry, '/')) {
            return false;
        }

        [$address, $prefix] = explode('/', $entry, 2);

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if ($prefix === '' || !ctype_digit($prefix)) {
            return false;
        }

        $maxPrefix = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 128 : 32;

        return (int) $prefix >= 0 && (int) $prefix <= $maxPrefix;
    }
}
