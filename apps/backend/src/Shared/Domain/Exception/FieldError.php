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

namespace App\Shared\Domain\Exception;

/**
 * A single field-level validation failure.
 *
 * Serialises to the `errors[]` item schema of the RFC 9457 envelope
 * (`docs/api/error-catalog.md`, OpenAPI `FieldError`). The `code` SHOULD be one
 * of the documented per-field codes (`required`, `invalid_format`, …).
 */
final readonly class FieldError
{
    public function __construct(
        public string $field,
        public string $code,
        public string $message,
    ) {
    }

    /**
     * @return array{field: string, code: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}
