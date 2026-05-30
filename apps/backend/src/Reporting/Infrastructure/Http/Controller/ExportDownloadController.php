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

namespace App\Reporting\Infrastructure\Http\Controller;

use App\Reporting\Infrastructure\Storage\LocalExportArtifactStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves a generated export file from a signed, time-limited download link.
 *
 * Authorisation is the signature itself: the link is minted only for callers who
 * already passed the GET-export access check, and the HMAC binds the artifact key
 * and deadline so it cannot be forged or replayed past expiry. The link is
 * therefore self-authenticating and the route is public (no bearer needed),
 * matching the OpenAPI "pre-signed download URL" contract.
 */
final readonly class ExportDownloadController
{
    public function __construct(
        private LocalExportArtifactStorage $storage,
    ) {
    }

    #[Route('/api/v1/exports/download', name: 'api_v1_exports_download', methods: ['GET'])]
    public function download(Request $request): Response
    {
        $key = (string) $request->query->get('key', '');
        $exp = $request->query->getInt('exp');
        $sig = (string) $request->query->get('sig', '');

        if ($key === '' || $sig === '' || !$this->storage->verify($key, $exp, $sig)) {
            return new Response('Invalid or expired download link.', Response::HTTP_FORBIDDEN);
        }

        $contents = $this->storage->read($key);
        if ($contents === null) {
            return new Response('Export not found.', Response::HTTP_NOT_FOUND);
        }

        return new Response($contents, Response::HTTP_OK, [
            'Content-Type' => $this->contentTypeFor($key),
            'Content-Disposition' => sprintf('attachment; filename="%s"', basename($key)),
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function contentTypeFor(string $key): string
    {
        return match (strtolower(pathinfo($key, PATHINFO_EXTENSION))) {
            'csv' => 'text/csv',
            'ndjson' => 'application/x-ndjson',
            default => 'application/octet-stream',
        };
    }
}
