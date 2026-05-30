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

namespace App\Reporting\Domain\Port;

/**
 * Driven port for persisting and serving generated export files.
 *
 * The default self-hosted adapter writes to a local directory and serves a
 * signed, time-limited download path; an alternative object-store adapter can
 * implement the same contract. Reporting deals only in opaque artifact keys.
 */
interface ExportArtifactStorage
{
    /**
     * Store the export payload and return the opaque artifact key under which it
     * was saved.
     *
     * @param non-empty-string $exportId
     *
     * @return non-empty-string
     */
    public function store(string $exportId, string $extension, string $contents): string;

    /**
     * A download URL for a stored artifact, valid until $expiresAtUnix. Returns
     * null when the artifact no longer exists or has expired.
     */
    public function downloadUrl(string $artifactKey, int $expiresAtUnix): ?string;
}
