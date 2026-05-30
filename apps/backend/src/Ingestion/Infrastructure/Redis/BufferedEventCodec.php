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

namespace App\Ingestion\Infrastructure\Redis;

use App\Ingestion\Domain\Model\BehavioralSignals;
use App\Ingestion\Domain\Model\BufferedEvent;
use App\Ingestion\Domain\Model\CanonicalEvent;
use App\Ingestion\Domain\Model\EventName;
use App\Ingestion\Domain\Model\RequestContext;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Serialises a BufferedEvent to a flat JSON string for the Redis Stream and
 * back. The identity inputs (ip / ua / accept-language) travel with the event
 * because the batch writer needs them to compute the salted ids; they live only
 * in the buffer and are dropped once the row is written.
 */
final class BufferedEventCodec
{
    public function encode(BufferedEvent $event): string
    {
        $payload = [
            'site_id' => $event->siteId,
            'event' => $this->encodeEvent($event->event),
            'context' => [
                'ip' => $event->context->ipAddress,
                'ua' => $event->context->userAgent,
                'lang' => $event->context->acceptLanguage,
                'origin' => $event->context->origin,
            ],
        ];

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    public function decode(string $json): BufferedEvent
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        /** @var array<string, mixed> $eventData */
        $eventData = $data['event'];
        /** @var array<string, mixed> $contextData */
        $contextData = $data['context'];

        $context = new RequestContext(
            ipAddress: is_scalar($contextData['ip'] ?? '') ? (string) ($contextData['ip'] ?? '') : '',
            userAgent: is_scalar($contextData['ua'] ?? '') ? (string) ($contextData['ua'] ?? '') : '',
            acceptLanguage: is_scalar($contextData['lang'] ?? '') ? (string) ($contextData['lang'] ?? '') : '',
            origin: isset($contextData['origin']) && is_scalar($contextData['origin']) ? (string) $contextData['origin'] : null,
        );

        $siteId = $data['site_id'];
        return new BufferedEvent(is_scalar($siteId) ? (string) $siteId : '', $this->decodeEvent($eventData), $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function encodeEvent(CanonicalEvent $event): array
    {
        return [
            'event_id' => $event->eventId,
            'site_key' => $event->siteKey,
            'event_name' => $event->eventName->value,
            'timestamp' => $event->timestamp->format('Y-m-d\TH:i:s.v\Z'),
            'seq' => $event->seq,
            'tracker_version' => $event->trackerVersion,
            'url' => $event->url,
            'pathname' => $event->pathname,
            'hostname' => $event->hostname,
            'referrer' => $event->referrer,
            'title' => $event->title,
            'screen_width' => $event->screenWidth,
            'screen_height' => $event->screenHeight,
            'viewport_width' => $event->viewportWidth,
            'viewport_height' => $event->viewportHeight,
            'device_pixel_ratio' => $event->devicePixelRatio,
            'connection_type' => $event->connectionType,
            'language' => $event->language,
            'timezone' => $event->timezone,
            'utm_source' => $event->utmSource,
            'utm_medium' => $event->utmMedium,
            'utm_campaign' => $event->utmCampaign,
            'utm_term' => $event->utmTerm,
            'utm_content' => $event->utmContent,
            'behavioral' => $this->encodeBehavioral($event->behavioral),
            'custom_properties' => $event->customProperties,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function decodeEvent(array $data): CanonicalEvent
    {
        return new CanonicalEvent(
            eventId: is_scalar($data['event_id']) ? (string) $data['event_id'] : '',
            siteKey: is_scalar($data['site_key']) ? (string) $data['site_key'] : '',
            eventName: EventName::from(is_scalar($data['event_name']) ? (string) $data['event_name'] : ''),
            timestamp: new DateTimeImmutable(is_scalar($data['timestamp']) ? (string) $data['timestamp'] : 'now', new DateTimeZone('UTC')),
            seq: is_numeric($data['seq']) ? (int) $data['seq'] : 0,
            trackerVersion: is_scalar($data['tracker_version']) ? (string) $data['tracker_version'] : '',
            url: is_scalar($data['url']) ? (string) $data['url'] : '',
            pathname: is_scalar($data['pathname']) ? (string) $data['pathname'] : '',
            hostname: is_scalar($data['hostname']) ? (string) $data['hostname'] : '',
            referrer: $this->nullableString($data, 'referrer'),
            title: $this->nullableString($data, 'title'),
            screenWidth: $this->nullableInt($data, 'screen_width'),
            screenHeight: $this->nullableInt($data, 'screen_height'),
            viewportWidth: $this->nullableInt($data, 'viewport_width'),
            viewportHeight: $this->nullableInt($data, 'viewport_height'),
            devicePixelRatio: $this->nullableFloat($data, 'device_pixel_ratio'),
            connectionType: $this->nullableString($data, 'connection_type'),
            language: $this->nullableString($data, 'language'),
            timezone: $this->nullableString($data, 'timezone'),
            utmSource: $this->nullableString($data, 'utm_source'),
            utmMedium: $this->nullableString($data, 'utm_medium'),
            utmCampaign: $this->nullableString($data, 'utm_campaign'),
            utmTerm: $this->nullableString($data, 'utm_term'),
            utmContent: $this->nullableString($data, 'utm_content'),
            behavioral: $this->decodeBehavioral($this->asStringKeyedArray($data['behavioral'] ?? null)),
            customProperties: $this->asScalarArray($data['custom_properties'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function encodeBehavioral(BehavioralSignals $b): array
    {
        return [
            'click_x' => $b->clickX,
            'click_y' => $b->clickY,
            'click_x_pct' => $b->clickXPct,
            'click_y_pct' => $b->clickYPct,
            'element_tag' => $b->elementTag,
            'element_text' => $b->elementText,
            'element_selector' => $b->elementSelector,
            'element_id' => $b->elementId,
            'scroll_depth_pct' => $b->scrollDepthPct,
            'scroll_depth_px' => $b->scrollDepthPx,
            'engagement_time_ms' => $b->engagementTimeMs,
            'is_rage_click' => $b->isRageClick,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function decodeBehavioral(array $data): BehavioralSignals
    {
        return new BehavioralSignals(
            clickX: $this->nullableInt($data, 'click_x'),
            clickY: $this->nullableInt($data, 'click_y'),
            clickXPct: $this->nullableFloat($data, 'click_x_pct'),
            clickYPct: $this->nullableFloat($data, 'click_y_pct'),
            elementTag: $this->nullableString($data, 'element_tag'),
            elementText: $this->nullableString($data, 'element_text'),
            elementSelector: $this->nullableString($data, 'element_selector'),
            elementId: $this->nullableString($data, 'element_id'),
            scrollDepthPct: $this->nullableInt($data, 'scroll_depth_pct'),
            scrollDepthPx: $this->nullableInt($data, 'scroll_depth_px'),
            engagementTimeMs: $this->nullableInt($data, 'engagement_time_ms'),
            isRageClick: (bool) ($data['is_rage_click'] ?? false),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function nullableString(array $data, string $key): ?string
    {
        $v = $data[$key] ?? null;
        return $v !== null && is_scalar($v) ? (string) $v : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function nullableInt(array $data, string $key): ?int
    {
        $v = $data[$key] ?? null;
        return $v !== null && is_numeric($v) ? (int) $v : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function nullableFloat(array $data, string $key): ?float
    {
        $v = $data[$key] ?? null;
        return $v !== null && is_numeric($v) ? (float) $v : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function asStringKeyedArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $result */
        $result = array_filter($value, static fn (mixed $v, mixed $k): bool => is_string($k), ARRAY_FILTER_USE_BOTH);
        return $result;
    }

    /**
     * @return array<string, bool|float|int|string>
     */
    private function asScalarArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $k => $v) {
            if (is_string($k) && is_scalar($v)) {
                $result[$k] = $v;
            }
        }

        return $result;
    }
}
