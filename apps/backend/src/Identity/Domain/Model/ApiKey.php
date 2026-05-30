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

use App\Identity\Domain\Exception\InvalidApiKeyScopeException;
use App\Identity\Domain\ValueObject\ApiKeyScope;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * A secret programmatic API key (postgres-schema.sql §6, ADR-0009 §4). Only the
 * SHA-256 hash and the 12-char display prefix are persisted; the raw key is shown
 * once at creation and never stored. Team-scoped, optionally restricted to a
 * subset of the team's sites (empty site set = all sites in the team).
 *
 * `scopes` and `site_ids` are lists of strings persisted as PostgreSQL native
 * arrays (TEXT[] / UUID[]). Persistence mapping is declared in the Infrastructure
 * layer (ADR-0004).
 */
class ApiKey
{
    public const PREFIX_LENGTH = 12;

    private readonly string $id;

    private readonly string $teamId;

    private ?string $createdBy = null;

    /**
     * @var list<string>
     */
    private readonly array $scopes;

    /**
     * @var list<string>
     */
    private readonly array $siteIds;

    private ?DateTimeImmutable $lastUsedAt = null;

    private ?DateTimeImmutable $revokedAt = null;

    /**
     * @param non-empty-list<ApiKeyScope> $scopes
     * @param list<Uuid>                  $siteIds
     */
    private function __construct(
        Uuid $id,
        Uuid $teamId,
        ?Uuid $createdBy,
        private readonly string $name,
        private readonly string $keyHash,
        private readonly string $keyPrefix,
        array $scopes,
        array $siteIds,
        private readonly ?DateTimeImmutable $expiresAt,
        private readonly DateTimeImmutable $createdAt,
    ) {
        if ($scopes === []) {
            throw InvalidApiKeyScopeException::empty();
        }

        $this->id = $id->getValue();
        $this->teamId = $teamId->getValue();
        $this->createdBy = $createdBy?->getValue();
        $this->scopes = array_values(array_unique(
            array_map(static fn (ApiKeyScope $scope): string => $scope->value, $scopes),
        ));
        $this->siteIds = array_values(array_map(static fn (Uuid $siteId): string => $siteId->getValue(), $siteIds));
    }

    /**
     * @param non-empty-list<ApiKeyScope> $scopes
     * @param list<Uuid>                  $siteIds
     */
    public static function issue(
        Uuid $id,
        Uuid $teamId,
        ?Uuid $createdBy,
        string $name,
        string $keyHash,
        string $keyPrefix,
        array $scopes,
        array $siteIds,
        ?DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): self {
        return new self(
            $id,
            $teamId,
            $createdBy,
            $name,
            $keyHash,
            $keyPrefix,
            $scopes,
            $siteIds,
            $expiresAt,
            $now,
        );
    }

    public function id(): Uuid
    {
        return Uuid::fromString($this->id);
    }

    public function teamId(): Uuid
    {
        return Uuid::fromString($this->teamId);
    }

    public function createdBy(): ?Uuid
    {
        return $this->createdBy === null ? null : Uuid::fromString($this->createdBy);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function keyHash(): string
    {
        return $this->keyHash;
    }

    public function keyPrefix(): string
    {
        return $this->keyPrefix;
    }

    /**
     * @return list<ApiKeyScope>
     */
    public function scopes(): array
    {
        return array_map(ApiKeyScope::from(...), $this->scopes);
    }

    public function hasScope(ApiKeyScope $scope): bool
    {
        return in_array($scope->value, $this->scopes, true);
    }

    /**
     * @return list<Uuid>
     */
    public function siteIds(): array
    {
        return array_map(Uuid::fromString(...), $this->siteIds);
    }

    public function isRestrictedToSites(): bool
    {
        return $this->siteIds !== [];
    }

    public function allowsSite(Uuid $siteId): bool
    {
        if (!$this->isRestrictedToSites()) {
            return true;
        }
        return in_array($siteId->getValue(), $this->siteIds, true);
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function lastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function recordUsage(DateTimeImmutable $now): void
    {
        $this->lastUsedAt = $now;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function revoke(DateTimeImmutable $now): void
    {
        if ($this->revokedAt !== null) {
            return;
        }

        $this->revokedAt = $now;
    }

    public function isExpired(DateTimeImmutable $at): bool
    {
        return $this->expiresAt !== null && $this->expiresAt <= $at;
    }

    /**
     * A key authenticates a request only if it is neither revoked nor expired.
     */
    public function isActive(DateTimeImmutable $at): bool
    {
        return !$this->isRevoked() && !$this->isExpired($at);
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
