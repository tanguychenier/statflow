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

namespace App\Ingestion\Application\Service;

use App\Ingestion\Domain\Model\BufferedEvent;
use App\Ingestion\Domain\Model\EnrichedEvent;
use App\Ingestion\Domain\Port\GeoLocatorPort;
use App\Ingestion\Domain\Service\IdentityHasher;
use App\Ingestion\Domain\Service\ReferrerClassifier;
use App\Ingestion\Domain\Service\SessionWindowResolver;
use App\Ingestion\Domain\Service\UserAgentParser;
use App\Ingestion\Domain\Service\UtmParser;

/**
 * Performs the enrichment the hot path deferred (architecture.md §101): salted
 * identity (ADR-0008), geo from the embedded DB, device from the User-Agent,
 * referrer source, and URL-derived UTM. Produces the row-ready EnrichedEvent.
 *
 * Identity is derived with the salt of the *event's own UTC day* so events that
 * cross midnight in the buffer are still bucketed correctly (ADR-0008 §3).
 */
final readonly class EventEnricher
{
    public function __construct(
        private IdentityHasher $identityHasher,
        private SessionWindowResolver $sessionWindowResolver,
        private GeoLocatorPort $geoLocator,
        private UserAgentParser $userAgentParser,
        private ReferrerClassifier $referrerClassifier,
        private UtmParser $utmParser,
    ) {
    }

    public function enrich(BufferedEvent $buffered): EnrichedEvent
    {
        $event = $this->utmParser->enrich($buffered->event);
        $context = $buffered->context;

        $utcDate = $event->timestamp->format('Y-m-d');
        $sessionWindow = $this->sessionWindowResolver->resolve($event->timestamp);

        $identity = $this->identityHasher->derive(
            $buffered->siteId,
            $context,
            $utcDate,
            $sessionWindow,
        );

        return new EnrichedEvent(
            siteId: $buffered->siteId,
            event: $event,
            identity: $identity,
            geo: $this->geoLocator->locate($context->ipAddress),
            device: $this->userAgentParser->parse($context->userAgent),
            referrerSource: $this->referrerClassifier->classify($event->referrer, $event->hostname),
        );
    }
}
