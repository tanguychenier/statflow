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

namespace App\Identity\Domain\Model;

use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Append-only record of a sensitive mutation (postgres-schema.sql §12). Rows are
 * never updated or deleted. The actor email is denormalised so the trail survives
 * the actor's account deletion. id is a BIGSERIAL assigned by PostgreSQL.
 * Persistence mapping is declared in the Infrastructure layer (ADR-0004).
 */
class AuditLogEntry
{
    /**
     * @phpstan-ignore property.unusedType (Doctrine assigns the int after persist)
     */
    private ?int $id = null;

    private readonly ?string $teamId;

    private readonly ?string $actorId;

    /**
     * @param array<string, mixed>|null $payload
     */
    public function __construct(
        ?Uuid $teamId,
        ?Uuid $actorId,
        private readonly ?string $actorEmail,
        private readonly string $action,
        private readonly ?string $resourceType,
        private readonly ?string $resourceId,
        private readonly ?array $payload,
        private readonly ?string $ipAddress,
        private readonly ?string $userAgent,
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->teamId = $teamId?->getValue();
        $this->actorId = $actorId?->getValue();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function teamId(): ?Uuid
    {
        return $this->teamId === null ? null : Uuid::fromString($this->teamId);
    }

    public function actorId(): ?Uuid
    {
        return $this->actorId === null ? null : Uuid::fromString($this->actorId);
    }

    public function actorEmail(): ?string
    {
        return $this->actorEmail;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function resourceType(): ?string
    {
        return $this->resourceType;
    }

    public function resourceId(): ?string
    {
        return $this->resourceId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function payload(): ?array
    {
        return $this->payload;
    }

    public function ipAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
