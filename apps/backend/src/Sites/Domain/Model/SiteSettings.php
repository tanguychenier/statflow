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

use App\Sites\Domain\ValueObject\AllowedDomainList;
use App\Sites\Domain\ValueObject\ExcludedIpList;
use App\Sites\Domain\ValueObject\SamplingRate;
use App\Sites\Domain\ValueObject\ScriptVariant;
use App\Sites\Domain\ValueObject\TrackerConfig;
use DateTimeImmutable;

/**
 * The rarely-read extended configuration of a {@see Site}, one-to-one with it
 * (PostgreSQL `site_settings`). Uses Doctrine's derived-identity idiom: the
 * primary key *is* the owning Site association, so the row shares the site's id
 * without mapping that column twice. Persistence mapping lives in Infrastructure
 * (ADR-0004); persisted state is plain primitives and behaviour exposes value
 * objects. The aggregate root is {@see Site}; settings are only mutated through it.
 */
class SiteSettings
{
    /**
     * @var list<string>
     */
    private array $allowedDomains;

    /**
     * @var list<string>
     */
    private array $excludedIps;

    private bool $stripQueryParams;

    private bool $customDomainEnabled;

    private bool $trackClicks;

    private bool $trackScroll;

    private bool $trackEngagementTime;

    private bool $trackOutboundLinks;

    private bool $hashBasedRouting;

    /**
     * @var list<string>
     */
    private array $ignoredSelectors;

    private string $samplingRate;

    private string $scriptVariant;

    private function __construct(
        private readonly Site $site,
        private DateTimeImmutable $updatedAt
    ) {
    }

    public static function default(Site $site, DateTimeImmutable $now): self
    {
        $settings = new self($site, $now);
        $settings->allowedDomains = [];
        $settings->excludedIps = [];
        $settings->stripQueryParams = false;
        $settings->customDomainEnabled = false;
        $settings->applyTrackerConfig(TrackerConfig::default());

        return $settings;
    }

    public function replace(
        AllowedDomainList $allowedDomains,
        ExcludedIpList $excludedIps,
        bool $stripQueryParams,
        bool $customDomainEnabled,
        TrackerConfig $trackerConfig,
        DateTimeImmutable $now,
    ): void {
        $this->allowedDomains = $allowedDomains->toArray();
        $this->excludedIps = $excludedIps->toArray();
        $this->stripQueryParams = $stripQueryParams;
        $this->customDomainEnabled = $customDomainEnabled;
        $this->applyTrackerConfig($trackerConfig);
        $this->updatedAt = $now;
    }

    public function siteId(): string
    {
        return $this->site->id()->getValue();
    }

    public function allowedDomains(): AllowedDomainList
    {
        return AllowedDomainList::fromArray($this->allowedDomains);
    }

    public function excludedIps(): ExcludedIpList
    {
        return ExcludedIpList::fromArray($this->excludedIps);
    }

    public function stripQueryParams(): bool
    {
        return $this->stripQueryParams;
    }

    public function customDomainEnabled(): bool
    {
        return $this->customDomainEnabled;
    }

    public function trackerConfig(): TrackerConfig
    {
        return TrackerConfig::create(
            trackClicks: $this->trackClicks,
            trackScroll: $this->trackScroll,
            trackEngagementTime: $this->trackEngagementTime,
            trackOutboundLinks: $this->trackOutboundLinks,
            hashBasedRouting: $this->hashBasedRouting,
            ignoredSelectors: $this->ignoredSelectors,
            samplingRate: SamplingRate::fromFloat((float) $this->samplingRate),
            scriptVariant: ScriptVariant::fromString($this->scriptVariant),
        );
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function applyTrackerConfig(TrackerConfig $config): void
    {
        $this->trackClicks = $config->trackClicks();
        $this->trackScroll = $config->trackScroll();
        $this->trackEngagementTime = $config->trackEngagementTime();
        $this->trackOutboundLinks = $config->trackOutboundLinks();
        $this->hashBasedRouting = $config->hashBasedRouting();
        $this->ignoredSelectors = $config->ignoredSelectors();
        $this->samplingRate = sprintf('%.3f', $config->samplingRate()->value());
        $this->scriptVariant = $config->scriptVariant()->value;
    }
}
