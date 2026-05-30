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

namespace App\Ingestion\Domain\Service;

/**
 * Performs the one transformation ADR-0007 §3 mandates at the ingestion
 * boundary: wire (short keys) → canonical (long keys). Runs before validation;
 * nothing downstream ever sees a short key.
 *
 * The transform is intentionally pure structure: it renames keys, converts the
 * Unix-millisecond `ts` to an ISO-8601 string, and nests behavioral signals
 * under the canonical names. It does not reject anything — type and presence
 * checks are the validator's job. A payload already in canonical form (a
 * server-side integration) is passed through with its `behavioral` and
 * `custom_properties` objects normalised to the canonical key set.
 */
final class WireToCanonicalNormalizer
{
    /**
     * Wire key → canonical key for top-level scalar fields (event-contract.md §2).
     */
    private const FIELD_MAP = [
        'eid' => 'event_id',
        'k' => 'site_key',
        'e' => 'event_name',
        'seq' => 'seq',
        'u' => 'url',
        'p' => 'pathname',
        'h' => 'hostname',
        'r' => 'referrer',
        't' => 'title',
        'vw' => 'viewport_width',
        'vh' => 'viewport_height',
        'sw' => 'screen_width',
        'sh' => 'screen_height',
        'dpr' => 'device_pixel_ratio',
        'ct' => 'connection_type',
        'tv' => 'tracker_version',
        'lang' => 'language',
        'tz' => 'timezone',
    ];

    /**
     * Wire key → canonical key for the behavioral sub-object (event-contract.md §5).
     */
    private const BEHAVIORAL_MAP = [
        'cx' => 'click_x',
        'cy' => 'click_y',
        'cxp' => 'click_x_pct',
        'cyp' => 'click_y_pct',
        'etag' => 'element_tag',
        'etxt' => 'element_text',
        'esel' => 'element_selector',
        'eid' => 'element_id',
        'sd' => 'scroll_depth_pct',
        'sdpx' => 'scroll_depth_px',
        'em' => 'engagement_time_ms',
        'rc' => 'is_rage_click',
    ];

    /**
     * @param array<string, mixed> $raw a single wire or canonical event object
     *
     * @return array<string, mixed> the same event in canonical form
     */
    public function normalize(array $raw): array
    {
        $canonical = [];

        foreach ($raw as $key => $value) {
            if ($key === 'ts') {
                $canonical['timestamp'] = $this->normalizeTimestamp($value);

                continue;
            }

            if ($key === 'b') {
                $canonical['behavioral'] = $this->normalizeBehavioral($value);

                continue;
            }

            if ($key === 'props') {
                $canonical['custom_properties'] = $value;

                continue;
            }

            $canonicalKey = self::FIELD_MAP[$key] ?? $key;
            $canonical[$canonicalKey] = $value;
        }

        if (isset($canonical['behavioral']) && is_array($canonical['behavioral'])) {
            $canonical['behavioral'] = $this->normalizeBehavioral($canonical['behavioral']);
        }

        return $canonical;
    }

    private function normalizeTimestamp(mixed $value): mixed
    {
        // Canonical/server-side payloads already send the ISO-8601 string.
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            $millis = (int) $value;
            $seconds = intdiv($millis, 1000);
            $fraction = abs($millis % 1000);

            return gmdate('Y-m-d\TH:i:s', $seconds)
                . sprintf('.%03dZ', $fraction);
        }

        // Leave anything else untouched so the validator can flag it.
        return $value;
    }

    private function normalizeBehavioral(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $signal) {
            $canonicalKey = self::BEHAVIORAL_MAP[$key] ?? $key;
            $out[$canonicalKey] = $signal;
        }

        return $out;
    }
}
