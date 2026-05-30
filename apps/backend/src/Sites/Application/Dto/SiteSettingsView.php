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

namespace App\Sites\Application\Dto;

use App\Sites\Domain\Model\Site;

/**
 * Read model for site settings, shaped to the OpenAPI `SiteSettings` schema
 * (including the nested `tracker_config`). Retention is read from the site
 * aggregate (it lives on the `sites` row), the rest from `site_settings`.
 */
final readonly class SiteSettingsView
{
    /**
     * @param list<string> $allowedDomains
     * @param list<string> $excludedIps
     * @param list<string> $ignoredSelectors
     */
    private function __construct(
        public array $allowedDomains,
        public array $excludedIps,
        public int $dataRetentionDays,
        public bool $stripQueryParams,
        public bool $customDomainEnabled,
        public bool $trackClicks,
        public bool $trackScroll,
        public bool $trackEngagementTime,
        public bool $trackOutboundLinks,
        public bool $hashBasedRouting,
        public array $ignoredSelectors,
        public float $samplingRate,
    ) {
    }

    public static function fromSite(Site $site): self
    {
        $settings = $site->settings();
        $tracker = $settings->trackerConfig();

        return new self(
            allowedDomains: $settings->allowedDomains()->toArray(),
            excludedIps: $settings->excludedIps()->toArray(),
            dataRetentionDays: $site->effectiveRetentionDays(),
            stripQueryParams: $settings->stripQueryParams(),
            customDomainEnabled: $settings->customDomainEnabled(),
            trackClicks: $tracker->trackClicks(),
            trackScroll: $tracker->trackScroll(),
            trackEngagementTime: $tracker->trackEngagementTime(),
            trackOutboundLinks: $tracker->trackOutboundLinks(),
            hashBasedRouting: $tracker->hashBasedRouting(),
            ignoredSelectors: $tracker->ignoredSelectors(),
            samplingRate: $tracker->samplingRate()->value(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allowed_domains' => $this->allowedDomains,
            'excluded_ips' => $this->excludedIps,
            'data_retention_days' => $this->dataRetentionDays,
            'strip_query_params' => $this->stripQueryParams,
            'custom_domain_enabled' => $this->customDomainEnabled,
            'tracker_config' => [
                'track_clicks' => $this->trackClicks,
                'track_scroll' => $this->trackScroll,
                'track_engagement_time' => $this->trackEngagementTime,
                'track_outbound_links' => $this->trackOutboundLinks,
                'hash_based_routing' => $this->hashBasedRouting,
                'ignored_selectors' => $this->ignoredSelectors,
                'sampling_rate' => $this->samplingRate,
            ],
        ];
    }
}
