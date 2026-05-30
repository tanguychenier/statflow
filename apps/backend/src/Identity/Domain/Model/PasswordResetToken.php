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
 * A single-use, time-limited password-reset token (ADR-0009 §2, openapi.yaml
 * /auth/forgot-password — 1-hour expiry). Only the SHA-256 hash of the opaque
 * token is stored, so a database leak does not expose usable reset links.
 *
 * This table is not in the v1 PostgreSQL schema dump; it is owned by the Identity
 * context and shipped via a migration registered by the wiring agent. Persistence
 * mapping is declared in the Infrastructure layer (ADR-0004).
 */
class PasswordResetToken
{
    private readonly string $id;

    private readonly string $userId;

    private ?DateTimeImmutable $consumedAt = null;

    public function __construct(
        Uuid $id,
        Uuid $userId,
        private readonly string $tokenHash,
        private readonly DateTimeImmutable $expiresAt,
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->id = $id->getValue();
        $this->userId = $userId->getValue();
    }

    public function id(): Uuid
    {
        return Uuid::fromString($this->id);
    }

    public function userId(): Uuid
    {
        return Uuid::fromString($this->userId);
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function consumedAt(): ?DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }

    public function isExpired(DateTimeImmutable $at): bool
    {
        return $this->expiresAt <= $at;
    }

    public function isUsable(DateTimeImmutable $at): bool
    {
        return !$this->isConsumed() && !$this->isExpired($at);
    }

    public function consume(DateTimeImmutable $now): void
    {
        if ($this->consumedAt !== null) {
            return;
        }

        $this->consumedAt = $now;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
