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

use App\Ingestion\Domain\Exception\EventValidationFailed;
use App\Ingestion\Domain\Exception\FieldViolation;
use App\Ingestion\Domain\Model\BehavioralSignals;
use App\Ingestion\Domain\Model\CanonicalEvent;
use App\Ingestion\Domain\Model\EventName;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Validates a canonical event array against the frozen contract (OpenAPI
 * `EventPayload`, event-contract.md §4–6) and builds a typed CanonicalEvent.
 *
 * Validation is total: every violation is collected and reported together so a
 * client fixes one payload, not one field per round trip. The factory only ever
 * returns a fully valid event or throws EventValidationFailed.
 */
final class CanonicalEventFactory
{
    private const UUID_PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    private const PROPERTY_KEY_PATTERN = '/^[a-z0-9_]{1,50}$/';

    private const MAX_DIMENSION = 20000;

    private const MAX_CUSTOM_PROPERTIES = 20;

    private const MAX_CUSTOM_PROPERTIES_BYTES = 4096;

    private const MAX_URL_LENGTH = 2048;

    private const MAX_HOSTNAME_LENGTH = 253;

    /**
     * @var list<FieldViolation>
     */
    private array $violations = [];

    /**
     * @param array<string, mixed> $data canonical event (post-normalisation)
     *
     * @throws EventValidationFailed
     */
    public function fromCanonical(array $data): CanonicalEvent
    {
        $this->violations = [];

        $eventId = $this->requireString($data, 'event_id');
        if ($eventId !== null && preg_match(self::UUID_PATTERN, $eventId) !== 1) {
            $this->fail('event_id', 'invalid_format', 'event_id must be a UUID.');
        }

        $siteKey = $this->requireString($data, 'site_key');
        $eventName = $this->requireEventName($data);

        $timestamp = $this->requireTimestamp($data);
        $seq = $this->requireSeq($data);
        $trackerVersion = $this->optionalString($data, 'tracker_version', 20) ?? '';

        $url = $this->requireString($data, 'url', self::MAX_URL_LENGTH);
        $pathname = $this->requireString($data, 'pathname', self::MAX_URL_LENGTH);
        $hostname = $this->requireString($data, 'hostname', self::MAX_HOSTNAME_LENGTH);

        if ($url !== null && filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->fail('url', 'invalid_format', 'url must be a valid absolute URI.');
        }

        $referrer = $this->optionalString($data, 'referrer', self::MAX_URL_LENGTH);
        $title = $this->optionalString($data, 'title', 512);

        $screenWidth = $this->optionalDimension($data, 'screen_width');
        $screenHeight = $this->optionalDimension($data, 'screen_height');
        $viewportWidth = $this->optionalDimension($data, 'viewport_width');
        $viewportHeight = $this->optionalDimension($data, 'viewport_height');

        $devicePixelRatio = $this->optionalFloat($data, 'device_pixel_ratio', 0.0, null);
        $connectionType = $this->optionalString($data, 'connection_type', 16);
        $language = $this->optionalString($data, 'language', 35);
        $timezone = $this->optionalString($data, 'timezone', 50);

        $utmSource = $this->optionalString($data, 'utm_source', 200);
        $utmMedium = $this->optionalString($data, 'utm_medium', 200);
        $utmCampaign = $this->optionalString($data, 'utm_campaign', 200);
        $utmTerm = $this->optionalString($data, 'utm_term', 200);
        $utmContent = $this->optionalString($data, 'utm_content', 200);

        $behavioral = $this->buildBehavioral($data);
        $customProperties = $this->buildCustomProperties($data);

        if ($this->violations !== []) {
            throw EventValidationFailed::withViolations($this->violations);
        }

        /** @var string $eventId */
        /** @var string $siteKey */
        /** @var EventName $eventName */
        /** @var DateTimeImmutable $timestamp */
        /** @var string $url */
        /** @var string $pathname */
        /** @var string $hostname */
        return new CanonicalEvent(
            eventId: $eventId,
            siteKey: $siteKey,
            eventName: $eventName,
            timestamp: $timestamp,
            seq: $seq ?? 0,
            trackerVersion: $trackerVersion,
            url: $url,
            pathname: $pathname,
            hostname: $hostname,
            referrer: $referrer,
            title: $title,
            screenWidth: $screenWidth,
            screenHeight: $screenHeight,
            viewportWidth: $viewportWidth,
            viewportHeight: $viewportHeight,
            devicePixelRatio: $devicePixelRatio,
            connectionType: $connectionType,
            language: $language,
            timezone: $timezone,
            utmSource: $utmSource,
            utmMedium: $utmMedium,
            utmCampaign: $utmCampaign,
            utmTerm: $utmTerm,
            utmContent: $utmContent,
            behavioral: $behavioral,
            customProperties: $customProperties,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireString(array $data, string $field, ?int $maxLength = null): ?string
    {
        if (!array_key_exists($field, $data)) {
            $this->fail($field, 'required', sprintf('%s is required.', $field));

            return null;
        }

        $value = $data[$field];
        if (!is_string($value)) {
            $this->fail($field, 'invalid_type', sprintf('%s must be a string.', $field));

            return null;
        }

        if ($value === '') {
            $this->fail($field, 'required', sprintf('%s must not be empty.', $field));

            return null;
        }

        if ($maxLength !== null && mb_strlen($value) > $maxLength) {
            $this->fail($field, 'max_length_exceeded', sprintf('%s exceeds %d characters.', $field, $maxLength));

            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalString(array $data, string $field, int $maxLength): ?string
    {
        if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
            return null;
        }

        $value = $data[$field];
        if (!is_string($value)) {
            $this->fail($field, 'invalid_type', sprintf('%s must be a string.', $field));

            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            $this->fail($field, 'max_length_exceeded', sprintf('%s exceeds %d characters.', $field, $maxLength));

            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireEventName(array $data): ?EventName
    {
        if (!array_key_exists('event_name', $data)) {
            $this->fail('event_name', 'required', 'event_name is required.');

            return null;
        }

        $value = $data['event_name'];
        if (!is_string($value)) {
            $this->fail('event_name', 'invalid_type', 'event_name must be a string.');

            return null;
        }

        $name = EventName::tryFrom($value);
        if ($name === null) {
            $this->fail('event_name', 'invalid_enum_value', sprintf('"%s" is not an accepted event name.', $value));

            return null;
        }

        return $name;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireTimestamp(array $data): ?DateTimeImmutable
    {
        if (!array_key_exists('timestamp', $data)) {
            $this->fail('timestamp', 'required', 'timestamp is required.');

            return null;
        }

        $value = $data['timestamp'];
        if (!is_string($value)) {
            $this->fail('timestamp', 'invalid_type', 'timestamp must be an ISO-8601 string.');

            return null;
        }

        $primaryParsed = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.v\Z', $value, new DateTimeZone('UTC'));
        $parsed = $primaryParsed !== false ? $primaryParsed : DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $value);

        if ($parsed === false) {
            $this->fail('timestamp', 'invalid_format', 'timestamp must be ISO-8601 (UTC, millisecond precision).');

            return null;
        }

        return $parsed->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireSeq(array $data): ?int
    {
        if (!array_key_exists('seq', $data)) {
            $this->fail('seq', 'required', 'seq is required.');

            return null;
        }

        $value = $data['seq'];
        if (!is_int($value) || $value < 0) {
            $this->fail('seq', 'out_of_range', 'seq must be a non-negative integer.');

            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalDimension(array $data, string $field): ?int
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }

        $value = $data[$field];
        if (!is_int($value)) {
            $this->fail($field, 'invalid_type', sprintf('%s must be an integer.', $field));

            return null;
        }

        if ($value < 0 || $value > self::MAX_DIMENSION) {
            $this->fail($field, 'out_of_range', sprintf('%s must be between 0 and %d.', $field, self::MAX_DIMENSION));

            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalFloat(array $data, string $field, ?float $min, ?float $max): ?float
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }

        $value = $data[$field];
        if (!is_int($value) && !is_float($value)) {
            $this->fail($field, 'invalid_type', sprintf('%s must be a number.', $field));

            return null;
        }

        $value = (float) $value;
        if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
            $this->fail($field, 'out_of_range', sprintf('%s is out of range.', $field));

            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildBehavioral(array $data): BehavioralSignals
    {
        if (!array_key_exists('behavioral', $data) || $data['behavioral'] === null) {
            return new BehavioralSignals();
        }

        $b = $data['behavioral'];
        if (!is_array($b)) {
            $this->fail('behavioral', 'invalid_type', 'behavioral must be an object.');

            return new BehavioralSignals();
        }

        /** @var array<string, mixed> $b */
        return new BehavioralSignals(
            clickX: $this->behavioralInt($b, 'click_x', 0, null),
            clickY: $this->behavioralInt($b, 'click_y', 0, null),
            clickXPct: $this->behavioralFloat($b, 'click_x_pct', 0.0, 100.0),
            clickYPct: $this->behavioralFloat($b, 'click_y_pct', 0.0, 100.0),
            elementTag: $this->behavioralString($b, 'element_tag', 50),
            elementText: $this->behavioralString($b, 'element_text', 200),
            elementSelector: $this->behavioralString($b, 'element_selector', 500),
            elementId: $this->behavioralString($b, 'element_id', 200),
            scrollDepthPct: $this->behavioralInt($b, 'scroll_depth_pct', 0, 100),
            scrollDepthPx: $this->behavioralInt($b, 'scroll_depth_px', 0, null),
            engagementTimeMs: $this->behavioralInt($b, 'engagement_time_ms', 0, null),
            isRageClick: $this->behavioralBool($b, 'is_rage_click'),
        );
    }

    /**
     * @param array<string, mixed> $b
     */
    private function behavioralInt(array $b, string $key, int $min, ?int $max): ?int
    {
        if (!array_key_exists($key, $b) || $b[$key] === null) {
            return null;
        }

        $value = $b[$key];
        if (!is_int($value)) {
            $this->fail('behavioral.' . $key, 'invalid_type', sprintf('%s must be an integer.', $key));

            return null;
        }

        if ($value < $min || ($max !== null && $value > $max)) {
            $this->fail('behavioral.' . $key, 'out_of_range', sprintf('%s is out of range.', $key));

            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $b
     */
    private function behavioralFloat(array $b, string $key, float $min, float $max): ?float
    {
        if (!array_key_exists($key, $b) || $b[$key] === null) {
            return null;
        }

        $value = $b[$key];
        if (!is_int($value) && !is_float($value)) {
            $this->fail('behavioral.' . $key, 'invalid_type', sprintf('%s must be a number.', $key));

            return null;
        }

        $value = (float) $value;
        if ($value < $min || $value > $max) {
            $this->fail('behavioral.' . $key, 'out_of_range', sprintf('%s must be between %g and %g.', $key, $min, $max));

            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $b
     */
    private function behavioralString(array $b, string $key, int $maxLength): ?string
    {
        if (!array_key_exists($key, $b) || $b[$key] === null || $b[$key] === '') {
            return null;
        }

        $value = $b[$key];
        if (!is_string($value)) {
            $this->fail('behavioral.' . $key, 'invalid_type', sprintf('%s must be a string.', $key));

            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }

    /**
     * @param array<string, mixed> $b
     */
    private function behavioralBool(array $b, string $key): bool
    {
        if (!array_key_exists($key, $b) || $b[$key] === null) {
            return false;
        }

        $value = $b[$key];
        if (!is_bool($value)) {
            $this->fail('behavioral.' . $key, 'invalid_type', sprintf('%s must be a boolean.', $key));

            return false;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string|int|float|bool>
     */
    private function buildCustomProperties(array $data): array
    {
        if (!array_key_exists('custom_properties', $data) || $data['custom_properties'] === null) {
            return [];
        }

        $props = $data['custom_properties'];
        if (!is_array($props) || array_is_list($props)) {
            $this->fail('custom_properties', 'invalid_type', 'custom_properties must be a flat object.');

            return [];
        }

        if (count($props) > self::MAX_CUSTOM_PROPERTIES) {
            $this->fail('custom_properties', 'out_of_range', sprintf('custom_properties may not exceed %d keys.', self::MAX_CUSTOM_PROPERTIES));
        }

        $clean = [];
        foreach ($props as $key => $value) {
            $field = 'custom_properties.' . $key;

            if (!is_string($key) || preg_match(self::PROPERTY_KEY_PATTERN, $key) !== 1) {
                $this->fail($field, 'invalid_format', 'custom property keys must match [a-z0-9_]{1,50}.');

                continue;
            }

            if (is_array($value)) {
                $this->fail($field, 'invalid_type', 'custom property values must be scalar (no nested objects).');

                continue;
            }

            if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
                $this->fail($field, 'invalid_type', 'custom property values must be string, number, or boolean.');

                continue;
            }

            if (is_string($value) && mb_strlen($value) > 1000) {
                $this->fail($field, 'max_length_exceeded', 'custom property string exceeds 1000 characters.');

                continue;
            }

            $clean[$key] = $value;
        }

        $serialized = json_encode($clean);
        if ($serialized !== false && strlen($serialized) > self::MAX_CUSTOM_PROPERTIES_BYTES) {
            $this->fail('custom_properties', 'out_of_range', sprintf('custom_properties serialised size exceeds %d bytes.', self::MAX_CUSTOM_PROPERTIES_BYTES));
        }

        return $clean;
    }

    private function fail(string $field, string $code, string $message): void
    {
        $this->violations[] = new FieldViolation($field, $code, $message);
    }
}
