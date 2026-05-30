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

namespace App\Reporting\Infrastructure\Storage;

use App\Reporting\Domain\Port\ExportArtifactStorage;
use RuntimeException;

/**
 * {@see ExportArtifactStorage} that writes export files to a local directory and
 * serves them through a signed, time-limited download route — the default for a
 * self-hosted install (no object store, no external service).
 *
 * The artifact key is the relative path under the base directory. A download URL
 * is the export-download route plus an `exp` deadline and an `sig` HMAC over the
 * key+deadline, so the link cannot be forged or extended past its TTL. The HTTP
 * download controller (owned by the app/wiring) verifies the same signature.
 */
final readonly class LocalExportArtifactStorage implements ExportArtifactStorage
{
    public function __construct(
        private string $baseDirectory,
        private string $signingSecret,
        private string $downloadBaseUrl = '/api/v1/exports/download',
    ) {
    }

    public function store(string $exportId, string $extension, string $contents): string
    {
        $this->assertReady();

        if (!is_dir($this->baseDirectory) && !@mkdir($this->baseDirectory, 0o750, true) && !is_dir($this->baseDirectory)) {
            throw new RuntimeException(sprintf('Export directory "%s" could not be created.', $this->baseDirectory));
        }

        $key = sprintf('%s.%s', $this->sanitise($exportId), $this->sanitise($extension));
        $path = $this->pathFor($key);

        if (@file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Export artifact "%s" could not be written.', $key));
        }

        return $key;
    }

    public function downloadUrl(string $artifactKey, int $expiresAtUnix): ?string
    {
        $path = $this->pathFor($artifactKey);

        if (!is_file($path)) {
            return null;
        }

        if ($expiresAtUnix <= time()) {
            return null;
        }

        $signature = $this->sign($artifactKey, $expiresAtUnix);

        return sprintf(
            '%s?key=%s&exp=%d&sig=%s',
            $this->downloadBaseUrl,
            rawurlencode($artifactKey),
            $expiresAtUnix,
            $signature,
        );
    }

    /**
     * Verify a download request's deadline and signature. Exposed so the HTTP
     * download controller validates links against the same secret.
     */
    public function verify(string $artifactKey, int $expiresAtUnix, string $signature): bool
    {
        if ($expiresAtUnix <= time()) {
            return false;
        }

        return hash_equals($this->sign($artifactKey, $expiresAtUnix), $signature);
    }

    public function read(string $artifactKey): ?string
    {
        $path = $this->pathFor($artifactKey);

        if (!is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    private function sign(string $artifactKey, int $expiresAtUnix): string
    {
        return hash_hmac('sha256', $artifactKey . '|' . $expiresAtUnix, $this->signingSecret);
    }

    private function pathFor(string $artifactKey): string
    {
        return rtrim($this->baseDirectory, '/') . '/' . $this->sanitise($artifactKey);
    }

    /**
     * Collapse the key to a flat, traversal-safe basename: only the safe
     * character class survives, so "../" and absolute paths cannot escape.
     */
    private function sanitise(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '', $value) ?? '';
    }

    private function assertReady(): void
    {
        if (trim($this->signingSecret) === '') {
            throw new RuntimeException('Export artifact signing secret is not configured.');
        }
    }
}
