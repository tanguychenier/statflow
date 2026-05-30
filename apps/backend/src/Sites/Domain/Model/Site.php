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

namespace App\Sites\Domain\Model;

use App\Shared\Domain\ValueObject\Uuid;
use App\Sites\Domain\ValueObject\AllowedDomainList;
use App\Sites\Domain\ValueObject\ExcludedIpList;
use App\Sites\Domain\ValueObject\Hostname;
use App\Sites\Domain\ValueObject\RetentionDays;
use App\Sites\Domain\ValueObject\SiteName;
use App\Sites\Domain\ValueObject\Timezone;
use App\Sites\Domain\ValueObject\TrackerConfig;
use App\Sites\Domain\ValueObject\TrackerKey;
use DateTimeImmutable;

/**
 * A tracked web property — the aggregate root of the Sites context.
 *
 * Owns its {@see SiteSettings} one-to-one and enforces every site invariant:
 * domain shape, tracker-key shape, retention bounds, and soft deletion. A site
 * has exactly one tracker key (ADR-0009 §1, postgres-schema.sql §4); rotating it
 * simply issues a new value and revokes the old one immediately. Persistence
 * mapping lives in Infrastructure (ADR-0004), so this class stays framework-
 * agnostic; persisted state is plain primitives and rich behaviour is exposed
 * through value-object accessors.
 */
class Site
{
    private readonly string $id;

    private readonly string $teamId;

    private string $name;

    private string $domain;

    private string $timezone;

    private bool $trackingEnabled;

    private string $trackerKey;

    private ?int $retentionDays = null;

    private DateTimeImmutable $updatedAt;

    private ?DateTimeImmutable $deletedAt = null;

    private readonly SiteSettings $settings;

    private function __construct(
        Uuid $id,
        Uuid $teamId,
        SiteName $name,
        Hostname $domain,
        Timezone $timezone,
        TrackerKey $trackerKey,
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->id = $id->getValue();
        $this->teamId = $teamId->getValue();
        $this->name = $name->value();
        $this->domain = $domain->value();
        $this->timezone = $timezone->value();
        $this->trackerKey = $trackerKey->value();
        $this->trackingEnabled = true;
        $this->updatedAt = $this->createdAt;
        $this->settings = SiteSettings::default($this, $this->createdAt);
    }

    public static function register(
        Uuid $id,
        Uuid $teamId,
        SiteName $name,
        Hostname $domain,
        Timezone $timezone,
        TrackerKey $trackerKey,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $teamId, $name, $domain, $timezone, $trackerKey, $now);
    }

    public function rename(SiteName $name, DateTimeImmutable $now): void
    {
        if ($this->name === $name->value()) {
            return;
        }

        $this->name = $name->value();
        $this->touch($now);
    }

    public function changeDomain(Hostname $domain, DateTimeImmutable $now): void
    {
        if ($this->domain === $domain->value()) {
            return;
        }

        $this->domain = $domain->value();
        $this->touch($now);
    }

    public function changeTimezone(Timezone $timezone, DateTimeImmutable $now): void
    {
        if ($this->timezone === $timezone->value()) {
            return;
        }

        $this->timezone = $timezone->value();
        $this->touch($now);
    }

    /**
     * Replace the public tracker key. The site keeps exactly one key, so the
     * outgoing value is overwritten and revoked immediately (ADR-0009 §1).
     */
    public function rotateTrackerKey(TrackerKey $newKey, DateTimeImmutable $now): void
    {
        $this->trackerKey = $newKey->value();
        $this->touch($now);
    }

    public function replaceSettings(
        AllowedDomainList $allowedDomains,
        ExcludedIpList $excludedIps,
        bool $stripQueryParams,
        bool $customDomainEnabled,
        ?RetentionDays $retentionDays,
        TrackerConfig $trackerConfig,
        DateTimeImmutable $now,
    ): void {
        $this->retentionDays = $retentionDays?->value();
        $this->settings->replace(
            allowedDomains: $allowedDomains,
            excludedIps: $excludedIps,
            stripQueryParams: $stripQueryParams,
            customDomainEnabled: $customDomainEnabled,
            trackerConfig: $trackerConfig,
            now: $now,
        );
        $this->touch($now);
    }

    public function softDelete(DateTimeImmutable $now): void
    {
        if ($this->deletedAt !== null) {
            return;
        }

        $this->deletedAt = $now;
        $this->trackingEnabled = false;
        $this->touch($now);
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function id(): Uuid
    {
        return Uuid::fromString($this->id);
    }

    public function teamId(): Uuid
    {
        return Uuid::fromString($this->teamId);
    }

    public function name(): SiteName
    {
        return SiteName::fromString($this->name);
    }

    public function domain(): Hostname
    {
        return Hostname::fromString($this->domain);
    }

    public function timezone(): Timezone
    {
        return Timezone::fromString($this->timezone);
    }

    public function trackerKey(): TrackerKey
    {
        return TrackerKey::fromString($this->trackerKey);
    }

    public function trackingEnabled(): bool
    {
        return $this->trackingEnabled;
    }

    public function retentionDays(): ?RetentionDays
    {
        return $this->retentionDays !== null ? RetentionDays::fromInt($this->retentionDays) : null;
    }

    public function effectiveRetentionDays(): int
    {
        return $this->retentionDays ?? RetentionDays::DEFAULT;
    }

    public function settings(): SiteSettings
    {
        return $this->settings;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    private function touch(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }
}
