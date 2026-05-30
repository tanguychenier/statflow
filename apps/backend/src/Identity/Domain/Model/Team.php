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

use App\Identity\Domain\Exception\TeamRuleViolationException;
use App\Identity\Domain\ValueObject\TeamSlug;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * The billing and access-control boundary (postgres-schema.sql §2). Each user
 * gets one personal team on sign-up plus any shared teams they are invited to.
 * Membership rows live in {@see TeamMembership}; ownerId denormalises the single
 * owner. Personal teams may not be deleted. Persistence mapping is declared in
 * the Infrastructure layer (ADR-0004).
 */
class Team
{
    private readonly string $id;

    private readonly string $slug;

    private string $ownerId;

    private string $plan = 'free';

    /**
     * @phpstan-ignore property.unusedType (reserved for billing; set by Doctrine on subscription activation)
     */
    private ?DateTimeImmutable $planExpiresAt = null;

    /**
     * @phpstan-ignore property.unusedType (reserved for billing; set by Doctrine on Stripe integration)
     */
    private ?string $stripeCustomerId = null;

    private int $monthlyEventQuota = 100000;

    private int $monthlyEventUsed = 0;

    private DateTimeImmutable $updatedAt;

    private ?DateTimeImmutable $deletedAt = null;

    private function __construct(
        Uuid $id,
        private string $name,
        TeamSlug $slug,
        Uuid $ownerId,
        private readonly bool $isPersonal,
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->id = $id->getValue();
        $this->slug = $slug->getValue();
        $this->ownerId = $ownerId->getValue();
        $this->updatedAt = $this->createdAt;
    }

    public static function createPersonal(
        Uuid $id,
        string $name,
        TeamSlug $slug,
        Uuid $ownerId,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $name, $slug, $ownerId, true, $now);
    }

    public static function createShared(
        Uuid $id,
        string $name,
        TeamSlug $slug,
        Uuid $ownerId,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $name, $slug, $ownerId, false, $now);
    }

    public function id(): Uuid
    {
        return Uuid::fromString($this->id);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rename(string $name, DateTimeImmutable $now): void
    {
        if ($this->name === $name) {
            return;
        }

        $this->name = $name;
        $this->touch($now);
    }

    public function slug(): TeamSlug
    {
        return TeamSlug::fromString($this->slug);
    }

    public function ownerId(): Uuid
    {
        return Uuid::fromString($this->ownerId);
    }

    public function transferOwnership(Uuid $newOwnerId, DateTimeImmutable $now): void
    {
        $this->ownerId = $newOwnerId->getValue();
        $this->touch($now);
    }

    public function isPersonal(): bool
    {
        return $this->isPersonal;
    }

    public function plan(): string
    {
        return $this->plan;
    }

    public function planExpiresAt(): ?DateTimeImmutable
    {
        return $this->planExpiresAt;
    }

    public function stripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function monthlyEventQuota(): int
    {
        return $this->monthlyEventQuota;
    }

    public function monthlyEventUsed(): int
    {
        return $this->monthlyEventUsed;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function softDelete(DateTimeImmutable $now): void
    {
        if ($this->isPersonal) {
            throw TeamRuleViolationException::cannotDeletePersonalTeam();
        }

        if ($this->deletedAt !== null) {
            return;
        }

        $this->deletedAt = $now;
        $this->touch($now);
    }

    private function touch(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }
}
