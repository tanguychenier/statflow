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
use App\Reporting\Domain\ValueObject\EmailAddress;
use App\Reporting\Domain\ValueObject\QueryDefinition;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * An asynchronous data-export job — the aggregate root for exports (OpenAPI
 * `Export`). Created `pending`, advanced to `processing` by the worker, then to
 * `completed` (with row count, byte size and an expiring artifact key) or
 * `failed` (with an error message).
 *
 * The aggregate enforces the linear state machine ({@see ExportStatus}); illegal
 * transitions raise rather than silently corrupt the lifecycle. The download URL
 * itself is minted by an infrastructure adapter from the stored artifact key.
 * Persistence mapping is declared in the Infrastructure layer (ADR-0004).
 */
class Export
{
    private readonly string $id;

    private readonly string $siteId;

    private string $status;

    private readonly string $format;

    /**
     * @var array<string, mixed>
     */
    private readonly array $query;

    private readonly ?string $notifyEmail;

    private readonly ?string $createdBy;

    private ?int $rowCount = null;

    private ?int $fileSizeBytes = null;

    private ?string $artifactKey = null;

    private ?DateTimeImmutable $expiresAt = null;

    private ?string $errorMessage = null;

    private ?DateTimeImmutable $completedAt = null;

    private function __construct(
        Uuid $id,
        Uuid $siteId,
        ExportFormat $format,
        QueryDefinition $query,
        ?EmailAddress $notifyEmail,
        ?Uuid $createdBy,
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->id = $id->getValue();
        $this->siteId = $siteId->getValue();
        $this->status = ExportStatus::Pending->value;
        $this->format = $format->value;
        $this->query = $query->toArray();
        $this->notifyEmail = $notifyEmail?->value();
        $this->createdBy = $createdBy?->getValue();
    }

    public static function request(
        Uuid $id,
        Uuid $siteId,
        ExportFormat $format,
        QueryDefinition $query,
        ?EmailAddress $notifyEmail,
        ?Uuid $createdBy,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $siteId, $format, $query, $notifyEmail, $createdBy, $now);
    }

    public function markProcessing(): void
    {
        $this->transitionTo(ExportStatus::Processing);
    }

    /**
     * @param non-empty-string $artifactKey
     */
    public function markCompleted(
        int $rowCount,
        int $fileSizeBytes,
        string $artifactKey,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $completedAt,
    ): void {
        $this->transitionTo(ExportStatus::Completed);

        $this->rowCount = $rowCount;
        $this->fileSizeBytes = $fileSizeBytes;
        $this->artifactKey = $artifactKey;
        $this->expiresAt = $expiresAt;
        $this->completedAt = $completedAt;
    }

    public function markFailed(string $errorMessage, DateTimeImmutable $failedAt): void
    {
        $this->transitionTo(ExportStatus::Failed);

        $this->errorMessage = $errorMessage;
        $this->completedAt = $failedAt;
    }

    public function id(): Uuid
    {
        return Uuid::fromString($this->id);
    }

    public function siteId(): Uuid
    {
        return Uuid::fromString($this->siteId);
    }

    public function status(): ExportStatus
    {
        return ExportStatus::from($this->status);
    }

    public function format(): ExportFormat
    {
        return ExportFormat::from($this->format);
    }

    /**
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return $this->query;
    }

    public function notifyEmail(): ?EmailAddress
    {
        return $this->notifyEmail !== null ? EmailAddress::fromString($this->notifyEmail) : null;
    }

    public function createdBy(): ?Uuid
    {
        return $this->createdBy !== null ? Uuid::fromString($this->createdBy) : null;
    }

    public function rowCount(): ?int
    {
        return $this->rowCount;
    }

    public function fileSizeBytes(): ?int
    {
        return $this->fileSizeBytes;
    }

    public function artifactKey(): ?string
    {
        return $this->artifactKey;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    private function transitionTo(ExportStatus $next): void
    {
        $current = ExportStatus::from($this->status);

        if (!$current->canTransitionTo($next)) {
            throw InvalidExportException::illegalTransition($current->value, $next->value);
        }

        $this->status = $next->value;
    }
}
