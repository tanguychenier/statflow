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

namespace App\Ingestion\Domain\Model;

use DateTimeImmutable;

/**
 * The canonical event model (event-contract.md §1). This is what every layer
 * downstream of the wire→canonical normaliser sees; short wire keys never
 * appear past that boundary.
 *
 * Server-derived identity, geo, and device fields are intentionally absent:
 * they are computed during enrichment (ADR-0007 §4, ADR-0008) and live on
 * EnrichedEvent, not here.
 */
final readonly class CanonicalEvent
{
    /**
     * @param array<string, string|int|float|bool> $customProperties
     */
    public function __construct(
        public string $eventId,
        public string $siteKey,
        public EventName $eventName,
        public DateTimeImmutable $timestamp,
        public int $seq,
        public string $trackerVersion,
        public string $url,
        public string $pathname,
        public string $hostname,
        public ?string $referrer,
        public ?string $title,
        public ?int $screenWidth,
        public ?int $screenHeight,
        public ?int $viewportWidth,
        public ?int $viewportHeight,
        public ?float $devicePixelRatio,
        public ?string $connectionType,
        public ?string $language,
        public ?string $timezone,
        public ?string $utmSource,
        public ?string $utmMedium,
        public ?string $utmCampaign,
        public ?string $utmTerm,
        public ?string $utmContent,
        public BehavioralSignals $behavioral,
        public array $customProperties,
    ) {
    }

    public function withUtm(
        ?string $source,
        ?string $medium,
        ?string $campaign,
        ?string $term,
        ?string $content,
    ): self {
        return new self(
            $this->eventId,
            $this->siteKey,
            $this->eventName,
            $this->timestamp,
            $this->seq,
            $this->trackerVersion,
            $this->url,
            $this->pathname,
            $this->hostname,
            $this->referrer,
            $this->title,
            $this->screenWidth,
            $this->screenHeight,
            $this->viewportWidth,
            $this->viewportHeight,
            $this->devicePixelRatio,
            $this->connectionType,
            $this->language,
            $this->timezone,
            $source,
            $medium,
            $campaign,
            $term,
            $content,
            $this->behavioral,
            $this->customProperties,
        );
    }
}
