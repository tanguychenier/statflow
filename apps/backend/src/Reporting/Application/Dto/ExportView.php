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

namespace App\Reporting\Application\Dto;

use App\Reporting\Domain\Model\Export;
use App\Reporting\Domain\Model\ExportStatus;
use App\Reporting\Domain\Port\ExportArtifactStorage;

/**
 * Read model for an export job, shaped to the OpenAPI `Export` schema.
 *
 * The download URL is minted on read from the stored artifact key so it is fresh
 * for the OpenAPI-mandated 1-hour validity window; it is present only when the
 * job has completed and the artifact has not yet expired.
 */
final readonly class ExportView
{
    /**
     * @param array<string, mixed> $query
     */
    private function __construct(
        public string $id,
        public string $siteId,
        public string $status,
        public string $format,
        public array $query,
        public ?int $rowCount,
        public ?int $fileSizeBytes,
        public ?string $downloadUrl,
        public ?string $expiresAt,
        public ?string $errorMessage,
        public string $createdAt,
        public ?string $completedAt,
    ) {
    }

    public static function fromExport(Export $export, ?ExportArtifactStorage $storage = null): self
    {
        $downloadUrl = null;
        $artifactKey = $export->artifactKey();
        $expiresAt = $export->expiresAt();

        if (
            $storage !== null
            && $export->status() === ExportStatus::Completed
            && $artifactKey !== null
            && $expiresAt !== null
        ) {
            $downloadUrl = $storage->downloadUrl($artifactKey, $expiresAt->getTimestamp());
        }

        return new self(
            id: $export->id()->getValue(),
            siteId: $export->siteId()->getValue(),
            status: $export->status()->value,
            format: $export->format()->value,
            query: $export->query(),
            rowCount: $export->rowCount(),
            fileSizeBytes: $export->fileSizeBytes(),
            downloadUrl: $downloadUrl,
            expiresAt: $expiresAt?->format(DATE_ATOM),
            errorMessage: $export->errorMessage(),
            createdAt: $export->createdAt()->format(DATE_ATOM),
            completedAt: $export->completedAt()?->format(DATE_ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->siteId,
            'status' => $this->status,
            'format' => $this->format,
            'query' => $this->query === [] ? new \stdClass() : $this->query,
            'row_count' => $this->rowCount,
            'file_size_bytes' => $this->fileSizeBytes,
            'download_url' => $this->downloadUrl,
            'expires_at' => $this->expiresAt,
            'error_message' => $this->errorMessage,
            'created_at' => $this->createdAt,
            'completed_at' => $this->completedAt,
        ];
    }
}
