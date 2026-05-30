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
 * A single field-level validation failure, mirroring the OpenAPI `FieldError`
 * schema (field / code / message). Codes are drawn from error-catalog.md §
 * "Validation Errors".
 */
final readonly class FieldViolation
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
