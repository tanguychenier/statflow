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

namespace App\Sites\Application\Command;

use App\Sites\Domain\Exception\InvalidSiteSettingsException;
use App\Sites\Domain\ValueObject\AllowedDomainList;
use App\Sites\Domain\ValueObject\ExcludedIpList;
use App\Sites\Domain\ValueObject\RetentionDays;
use App\Sites\Domain\ValueObject\SamplingRate;
use App\Sites\Domain\ValueObject\ScriptVariant;
use App\Sites\Domain\ValueObject\TrackerConfig;

/**
 * Translates the raw PUT SiteSettings body into validated value objects,
 * applying the documented defaults for every omitted field (true PUT/replace
 * semantics). Centralises the array-to-domain conversion so handlers stay thin
 * and every malformed scalar is rejected with a consistent message.
 */
final readonly class SiteSettingsInput
{
    private function __construct(
        public AllowedDomainList $allowedDomains,
        public ExcludedIpList $excludedIps,
        public ?RetentionDays $retentionDays,
        public bool $stripQueryParams,
        public bool $customDomainEnabled,
        public TrackerConfig $trackerConfig,
    ) {
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            allowedDomains: AllowedDomainList::fromArray(self::stringList($raw, 'allowed_domains')),
            excludedIps: ExcludedIpList::fromArray(self::stringList($raw, 'excluded_ips')),
            retentionDays: self::retention($raw),
            stripQueryParams: self::bool($raw, 'strip_query_params', false),
            customDomainEnabled: self::bool($raw, 'custom_domain_enabled', false),
            trackerConfig: self::trackerConfig(self::subArray($raw, 'tracker_config')),
        );
    }

    /**
     * @param array<string, mixed> $raw
     */
    private static function trackerConfig(array $raw): TrackerConfig
    {
        return TrackerConfig::create(
            trackClicks: self::bool($raw, 'track_clicks', true),
            trackScroll: self::bool($raw, 'track_scroll', true),
            trackEngagementTime: self::bool($raw, 'track_engagement_time', true),
            trackOutboundLinks: self::bool($raw, 'track_outbound_links', true),
            hashBasedRouting: self::bool($raw, 'hash_based_routing', false),
            ignoredSelectors: self::stringList($raw, 'ignored_selectors'),
            samplingRate: self::samplingRate($raw),
            scriptVariant: self::scriptVariant($raw),
        );
    }

    /**
     * @param array<string, mixed> $raw
     */
    private static function retention(array $raw): ?RetentionDays
    {
        if (!array_key_exists('data_retention_days', $raw) || $raw['data_retention_days'] === null) {
            return null;
        }

        $value = $raw['data_retention_days'];

        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw InvalidSiteSettingsException::notInteger('data_retention_days');
        }

        return RetentionDays::fromInt((int) $value);
    }

    /**
     * @param array<string, mixed> $raw
     */
    private static function samplingRate(array $raw): SamplingRate
    {
        if (!array_key_exists('sampling_rate', $raw) || $raw['sampling_rate'] === null) {
            return SamplingRate::default();
        }

        $value = $raw['sampling_rate'];

        if (!is_int($value) && !is_float($value)) {
            throw InvalidSiteSettingsException::notNumber('sampling_rate');
        }

        return SamplingRate::fromFloat((float) $value);
    }

    /**
     * @param array<string, mixed> $raw
     */
    private static function scriptVariant(array $raw): ScriptVariant
    {
        if (!array_key_exists('script_variant', $raw) || $raw['script_variant'] === null) {
            return ScriptVariant::Default;
        }

        if (!is_string($raw['script_variant'])) {
            throw InvalidSiteSettingsException::notString('script_variant');
        }

        return ScriptVariant::fromString($raw['script_variant']);
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return list<string>
     */
    private static function stringList(array $raw, string $key): array
    {
        if (!array_key_exists($key, $raw) || $raw[$key] === null) {
            return [];
        }

        if (!is_array($raw[$key])) {
            throw InvalidSiteSettingsException::notArray($key);
        }

        $out = [];
        foreach ($raw[$key] as $item) {
            if (!is_string($item)) {
                throw InvalidSiteSettingsException::notStringList($key);
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>
     */
    private static function subArray(array $raw, string $key): array
    {
        if (!array_key_exists($key, $raw) || $raw[$key] === null) {
            return [];
        }

        if (!is_array($raw[$key])) {
            throw InvalidSiteSettingsException::notArray($key);
        }

        /** @var array<string, mixed> $sub */
        $sub = $raw[$key];

        return $sub;
    }

    /**
     * @param array<string, mixed> $raw
     */
    private static function bool(array $raw, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $raw) || $raw[$key] === null) {
            return $default;
        }

        if (!is_bool($raw[$key])) {
            throw InvalidSiteSettingsException::notBool($key);
        }

        return $raw[$key];
    }
}
