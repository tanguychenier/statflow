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

namespace App\Reporting\Domain\Model;

use App\Reporting\Domain\Exception\InvalidExportException;

/**
 * Serialisation format of a data export, per the OpenAPI `Export.format` enum.
 */
enum ExportFormat: string
{
    case Csv = 'csv';
    case Ndjson = 'ndjson';

    public static function fromString(string $value): self
    {
        $format = self::tryFrom($value);

        if ($format === null) {
            throw InvalidExportException::unknownFormat($value);
        }

        return $format;
    }

    /**
     * Media type emitted for the generated file.
     */
    public function contentType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv',
            self::Ndjson => 'application/x-ndjson',
        };
    }

    public function fileExtension(): string
    {
        return $this->value;
    }
}
