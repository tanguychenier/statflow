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

namespace App\Tests\Unit\Reporting\Fake;

use App\Reporting\Domain\Port\ExportArtifactStorage;

/**
 * In-memory {@see ExportArtifactStorage} that records stored payloads and mints
 * a deterministic, opaque download URL for assertions.
 */
final class InMemoryExportArtifactStorage implements ExportArtifactStorage
{
    /**
     * @var array<string, string> artifact key => contents
     */
    public array $stored = [];

    public function store(string $exportId, string $extension, string $contents): string
    {
        $key = sprintf('%s.%s', $exportId, $extension);
        $this->stored[$key] = $contents;

        return $key;
    }

    public function downloadUrl(string $artifactKey, int $expiresAtUnix): ?string
    {
        if (!isset($this->stored[$artifactKey])) {
            return null;
        }

        return sprintf('https://exports.test/%s?exp=%d', $artifactKey, $expiresAtUnix);
    }
}
