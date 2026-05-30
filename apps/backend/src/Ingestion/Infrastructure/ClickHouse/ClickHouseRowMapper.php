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

namespace App\Ingestion\Infrastructure\ClickHouse;

use App\Ingestion\Domain\Model\EnrichedEvent;

/**
 * Maps an EnrichedEvent to one ClickHouse `events` row (clickhouse-schema.sql
 * §1) in the JSONEachRow shape.
 *
 * Two contract rules live here (event-contract.md §2.2, §6):
 *  - properties is Map(String,String): numbers and booleans are coerced to their
 *    canonical string form (`true`/`false`, shortest round-trippable decimal);
 *  - custom_properties.revenue / .currency are promoted into the dedicated
 *    `revenue` / `currency` columns and removed from the properties map.
 */
final class ClickHouseRowMapper
{
    /**
     * Maximum significant digits an IEEE-754 double can require to round-trip.
     */
    private const MAX_DOUBLE_PRECISION = 17;

    /**
     * @return array<string, string|int|float|bool|null|array<string, string>>
     */
    public function toRow(EnrichedEvent $enriched): array
    {
        $event = $enriched->event;
        $behavioral = $event->behavioral;

        [$properties, $revenue, $currency] = $this->mapProperties($event->customProperties);

        return [
            'site_id' => $enriched->siteId,
            'event_id' => $event->eventId,
            'event_name' => $event->eventName->value,
            'timestamp' => $event->timestamp->format('Y-m-d H:i:s.v'),
            'seq' => $event->seq,
            'tracker_version' => $event->trackerVersion,
            'visitor_id' => $enriched->identity->visitorId,
            'session_id' => $enriched->identity->sessionId,

            'hostname' => $event->hostname,
            'pathname' => $event->pathname,
            'referrer' => $event->referrer ?? '',
            'referrer_source' => $enriched->referrerSource,

            'utm_source' => $event->utmSource ?? '',
            'utm_medium' => $event->utmMedium ?? '',
            'utm_campaign' => $event->utmCampaign ?? '',
            'utm_term' => $event->utmTerm ?? '',
            'utm_content' => $event->utmContent ?? '',

            'country_code' => $enriched->geo->countryCode,
            'region' => $enriched->geo->region,
            'city' => $enriched->geo->city,

            'device_type' => $enriched->device->deviceType,
            'browser' => $enriched->device->browser,
            'browser_version' => $enriched->device->browserVersion,
            'os' => $enriched->device->os,
            'os_version' => $enriched->device->osVersion,
            'screen_width' => $event->screenWidth ?? 0,
            'screen_height' => $event->screenHeight ?? 0,
            'language' => $event->language ?? '',

            'click_x' => $behavioral->clickX,
            'click_y' => $behavioral->clickY,
            'click_x_pct' => $behavioral->clickXPct,
            'click_y_pct' => $behavioral->clickYPct,
            'viewport_width' => $event->viewportWidth,
            'viewport_height' => $event->viewportHeight,
            'scroll_depth_px' => $behavioral->scrollDepthPx,
            'scroll_depth_pct' => $behavioral->scrollDepthPct,
            'engagement_time_ms' => $behavioral->engagementTimeMs,
            'is_rage_click' => $behavioral->isRageClick ? 1 : 0,
            'element_selector' => $behavioral->elementSelector,
            'element_text' => $behavioral->elementText,

            'properties' => $properties,
            'revenue' => $revenue,
            'currency' => $currency,
        ];
    }

    /**
     * @param array<string, string|int|float|bool> $custom
     *
     * @return array{0: array<string, string>, 1: ?string, 2: string}
     */
    private function mapProperties(array $custom): array
    {
        $revenue = null;
        $currency = '';
        $properties = [];

        foreach ($custom as $key => $value) {
            if ($key === 'revenue' && (is_int($value) || is_float($value))) {
                $revenue = $this->numberToString($value);

                continue;
            }

            if ($key === 'currency' && is_string($value)) {
                $currency = $value;

                continue;
            }

            $properties[$key] = $this->scalarToString($value);
        }

        return [$properties, $revenue, $currency];
    }

    private function scalarToString(string|int|float|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return $this->numberToString($value);
        }

        return $value;
    }

    /**
     * Formats a number as the shortest decimal that round-trips back to the same
     * value (event-contract.md §2.2). For floats this scans precisions from 1 to
     * the IEEE-754 double maximum of 17 significant digits and returns the first
     * representation that parses back to the identical float, so the output is
     * deterministic regardless of the runtime's `serialize_precision` ini setting.
     */
    private function numberToString(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_nan($value) || is_infinite($value)) {
            return $value > 0 ? 'inf' : (is_nan($value) ? 'nan' : '-inf');
        }

        for ($precision = 1; $precision < self::MAX_DOUBLE_PRECISION; ++$precision) {
            $candidate = sprintf('%.' . $precision . 'g', $value);
            if ((float) $candidate === $value) {
                return $this->normalizeDecimal($candidate);
            }
        }

        return $this->normalizeDecimal(sprintf('%.' . self::MAX_DOUBLE_PRECISION . 'g', $value));
    }

    /**
     * Folds `%g`'s exponential notation back into a plain decimal so the stored
     * string is human-stable (e.g. `1.0E-5` → `0.00001`), keeping the canonical
     * form free of locale- and platform-specific exponent spellings.
     */
    private function normalizeDecimal(string $formatted): string
    {
        if (stripos($formatted, 'e') === false) {
            return $formatted;
        }

        $float = (float) $formatted;
        $plain = rtrim(rtrim(sprintf('%.17f', $float), '0'), '.');

        return (float) $plain === $float ? $plain : $formatted;
    }
}
