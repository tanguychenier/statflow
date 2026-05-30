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

namespace App\Reporting\Domain\Service;

use App\Reporting\Domain\Model\ExportFormat;

/**
 * Serialises export result rows to the requested wire format. Pure and
 * dependency-free so it is fully unit-testable and reusable from the worker.
 *
 * CSV follows RFC 4180: comma-separated, CRLF line endings, fields containing a
 * comma, quote, CR or LF are double-quoted and embedded quotes are doubled. A
 * header row is emitted from the union of keys, preserving first-seen order so
 * the output is stable. NDJSON emits one compact JSON object per line.
 */
final class ExportRowSerializer
{
    /**
     * @param list<array<string, scalar|null>> $rows
     */
    public function serialize(ExportFormat $format, array $rows): string
    {
        return match ($format) {
            ExportFormat::Csv => $this->toCsv($rows),
            ExportFormat::Ndjson => $this->toNdjson($rows),
        };
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     */
    private function toCsv(array $rows): string
    {
        $columns = $this->columns($rows);

        if ($columns === []) {
            return '';
        }

        $lines = [$this->csvLine($columns)];

        foreach ($rows as $row) {
            $cells = [];
            foreach ($columns as $column) {
                $cells[] = $this->csvCell($row[$column] ?? null);
            }
            $lines[] = $this->csvLine($cells);
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     */
    private function toNdjson(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $lines = array_map(
            static fn (array $row): string => (string) json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $rows,
        );

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     *
     * @return list<string>
     */
    private function columns(array $rows): array
    {
        $columns = [];

        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                $columns[$key] = true;
            }
        }

        return array_keys($columns);
    }

    /**
     * @param list<string> $cells
     */
    private function csvLine(array $cells): string
    {
        return implode(',', $cells);
    }

    private function csvCell(string|int|float|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $string = (string) $value;

        if (preg_match('/[",\r\n]/', $string) === 1) {
            return '"' . str_replace('"', '""', $string) . '"';
        }

        return $string;
    }
}
