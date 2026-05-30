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
 * One or more canonical-event fields failed validation
 * (error-catalog.md `validation-failed`, HTTP 422).
 *
 * Aggregates every field violation so a caller (single or batch) sees the full
 * set in one pass rather than fixing errors one at a time.
 */
final class EventValidationFailed extends IngestionException
{
    /**
     * @param list<FieldViolation> $violations
     */
    private function __construct(
        private readonly array $violations,
    ) {
        parent::__construct('Event validation failed.');
    }

    /**
     * @param list<FieldViolation> $violations
     */
    public static function withViolations(array $violations): self
    {
        return new self($violations);
    }

    /**
     * @return list<FieldViolation>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    public function slug(): string
    {
        return 'validation-failed';
    }

    public function title(): string
    {
        return 'Validation Failed';
    }

    public function status(): int
    {
        return 422;
    }
}
