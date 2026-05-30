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

use App\Ingestion\Domain\Exception\FieldViolation;

/**
 * The outcome for a single event within a batch, mapped to one entry of the
 * OpenAPI BatchResultResponse `results` array (index / status / code / errors).
 *
 * A "skipped" event (idempotent replay, bot, excluded IP/country) counts as
 * accepted for the caller: the contract returns 204 for replays, so a replay is
 * not a rejection the client should retry.
 */
final readonly class EventResult
{
    /**
     * @param list<FieldViolation> $errors
     */
    private function __construct(
        public int $index,
        public bool $accepted,
        public ?string $code,
        public array $errors,
    ) {
    }

    public static function accepted(int $index): self
    {
        return new self($index, true, null, []);
    }

    /**
     * @param list<FieldViolation> $errors
     */
    public static function rejected(int $index, string $code, array $errors = []): self
    {
        return new self($index, false, $code, $errors);
    }

    /**
     * @return array{index: int, status: string, code?: string, errors?: list<array{field: string, code: string, message: string}>}
     */
    public function toArray(): array
    {
        if ($this->accepted) {
            return [
                'index' => $this->index,
                'status' => 'accepted',
            ];
        }

        $out = [
            'index' => $this->index,
            'status' => 'rejected',
            'code' => (string) $this->code,
        ];
        if ($this->errors !== []) {
            $out['errors'] = array_map(static fn (FieldViolation $v): array => $v->toArray(), $this->errors);
        }

        return $out;
    }
}
