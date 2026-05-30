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

use App\Identity\Domain\ValueObject\TeamRole;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Join record linking a user to a team with a role (postgres-schema.sql §3).
 * acceptedAt is NULL while an invitation is pending; status derives from it.
 * Persistence mapping is declared in the Infrastructure layer (ADR-0004).
 */
class TeamMembership
{
    private readonly string $id;

    private readonly string $teamId;

    private readonly string $userId;

    private ?string $invitedBy = null;

    private ?DateTimeImmutable $acceptedAt = null;

    private readonly DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    private function __construct(
        Uuid $id,
        Uuid $teamId,
        Uuid $userId,
        private TeamRole $role,
        ?Uuid $invitedBy,
        private readonly DateTimeImmutable $invitedAt,
    ) {
        $this->id = $id->getValue();
        $this->teamId = $teamId->getValue();
        $this->userId = $userId->getValue();
        $this->invitedBy = $invitedBy?->getValue();
        $this->createdAt = $this->invitedAt;
        $this->updatedAt = $this->invitedAt;
    }

    /**
     * The founding membership of a team: the owner, already accepted.
     */
    public static function founder(Uuid $id, Uuid $teamId, Uuid $userId, DateTimeImmutable $now): self
    {
        $membership = new self($id, $teamId, $userId, TeamRole::Owner, null, $now);
        $membership->acceptedAt = $now;

        return $membership;
    }

    /**
     * A pending invitation. acceptedAt stays NULL until the invitee accepts.
     */
    public static function invite(
        Uuid $id,
        Uuid $teamId,
        Uuid $userId,
        TeamRole $role,
        Uuid $invitedBy,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $teamId, $userId, $role, $invitedBy, $now);
    }

    public function id(): Uuid
    {
        return Uuid::fromString($this->id);
    }

    public function teamId(): Uuid
    {
        return Uuid::fromString($this->teamId);
    }

    public function userId(): Uuid
    {
        return Uuid::fromString($this->userId);
    }

    public function role(): TeamRole
    {
        return $this->role;
    }

    public function changeRole(TeamRole $role, DateTimeImmutable $now): void
    {
        if ($this->role === $role) {
            return;
        }

        $this->role = $role;
        $this->touch($now);
    }

    public function isOwner(): bool
    {
        return $this->role === TeamRole::Owner;
    }

    public function invitedBy(): ?Uuid
    {
        return $this->invitedBy === null ? null : Uuid::fromString($this->invitedBy);
    }

    public function invitedAt(): DateTimeImmutable
    {
        return $this->invitedAt;
    }

    public function acceptedAt(): ?DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function isPending(): bool
    {
        return $this->acceptedAt === null;
    }

    public function accept(DateTimeImmutable $now): void
    {
        if ($this->acceptedAt !== null) {
            return;
        }

        $this->acceptedAt = $now;
        $this->touch($now);
    }

    public function status(): string
    {
        return $this->isPending() ? 'invited' : 'active';
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }
}
