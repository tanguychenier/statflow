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

namespace App\Reporting\Domain\ValueObject;

use App\Reporting\Domain\Exception\InvalidReportDefinitionException;

/**
 * The serialised Analytics query a saved report or export carries.
 *
 * The Reporting context deliberately does not parse or validate the query's
 * internal shape: that is the Analytics context's responsibility when the query
 * is replayed (architecture.md "Context interaction rules"). Reporting only
 * guarantees the payload is a JSON object and bounds its serialised size so a
 * pathological definition cannot bloat the row.
 */
final readonly class QueryDefinition
{
    public const MAX_SERIALISED_BYTES = 32_768;

    /**
     * @param array<string, mixed> $values
     */
    private function __construct(
        private array $values,
    ) {
    }

    /**
     * @param array<array-key, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        if ($values !== [] && array_is_list($values)) {
            throw InvalidReportDefinitionException::notAnObject();
        }

        $encoded = json_encode($values);
        if ($encoded === false) {
            throw InvalidReportDefinitionException::notSerialisable();
        }

        if (strlen($encoded) > self::MAX_SERIALISED_BYTES) {
            throw InvalidReportDefinitionException::tooLarge(self::MAX_SERIALISED_BYTES);
        }

        /** @var array<string, mixed> $values */
        return new self($values);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
