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

namespace App\Ingestion\Application\Handler;

use App\Ingestion\Application\Command\IngestEventsCommand;
use App\Ingestion\Application\Dto\EventResult;
use App\Ingestion\Application\Dto\IngestionResult;
use App\Ingestion\Domain\Exception\EventValidationFailed;
use App\Ingestion\Domain\Exception\InvalidTrackerKey;
use App\Ingestion\Domain\Exception\MalformedRequest;
use App\Ingestion\Domain\Exception\RateLimitExceeded;
use App\Ingestion\Domain\Model\BufferedEvent;
use App\Ingestion\Domain\Model\CanonicalEvent;
use App\Ingestion\Domain\Model\RequestContext;
use App\Ingestion\Domain\Model\Site;
use App\Ingestion\Domain\Port\EventBufferPort;
use App\Ingestion\Domain\Port\IdempotencyStorePort;
use App\Ingestion\Domain\Port\RateLimiterPort;
use App\Ingestion\Domain\Port\SiteRepositoryPort;
use App\Ingestion\Domain\Service\BotDetector;
use App\Ingestion\Domain\Service\CanonicalEventFactory;
use App\Ingestion\Domain\Service\OriginAuthorizer;
use App\Ingestion\Domain\Service\OriginDecision;
use App\Ingestion\Domain\Service\WireToCanonicalNormalizer;
use App\Ingestion\Domain\ValueObject\SiteKey;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Drives the synchronous ingestion path. Request-scoped controls (auth, origin
 * allowlist, rate limit) are checked once for the whole request and fail it
 * outright; per-event controls (validation, idempotency, bot filtering) only
 * affect the offending event so a partly-bad batch still buffers its good
 * events (openapi.yaml /events/batch 200 semantics).
 *
 * Enrichment (identity HMAC, geo, device, referrer source) is deliberately
 * deferred to the batch writer to keep this path lean (architecture.md §101).
 */
final readonly class IngestEventsHandler
{
    private LoggerInterface $logger;

    public function __construct(
        private SiteRepositoryPort $sites,
        private RateLimiterPort $rateLimiter,
        private IdempotencyStorePort $idempotency,
        private EventBufferPort $buffer,
        private WireToCanonicalNormalizer $normalizer,
        private CanonicalEventFactory $eventFactory,
        private OriginAuthorizer $originAuthorizer,
        private BotDetector $botDetector,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(IngestEventsCommand $command): IngestionResult
    {
        if ($command->rawEvents === []) {
            throw MalformedRequest::emptyBatch();
        }

        $site = $this->authenticate($command->rawEvents);
        $this->authorizeOrigin($site, $command->context->origin);
        $this->enforceRateLimit($site, count($command->rawEvents));

        return $this->processEvents($command->rawEvents, $command->context, $site);
    }

    /**
     * Checks the origin allowlist and surfaces the permissive bootstrap path.
     * An unconfigured allowlist accepts any origin by design (so a fresh site
     * tracks immediately), but that is an insecure first-run state, so we log it
     * at warning level instead of letting it pass silently.
     */
    private function authorizeOrigin(Site $site, ?string $origin): void
    {
        $decision = $this->originAuthorizer->assertAllowed($site, $origin);

        if ($decision === OriginDecision::BootstrapAllowAll) {
            $this->logger->warning(
                'Accepting event for a site with no origin allowlist; any origin is permitted until one is configured.',
                [
                    'site_id' => $site->id,
                    'origin' => $origin,
                ],
            );
        }
    }

    /**
     * Resolves the site from the shared site_key. All events in one request
     * belong to one site (one tracker); we authenticate on the first event's
     * key and reject events that disagree.
     *
     * @param list<array<string, mixed>> $rawEvents
     */
    private function authenticate(array $rawEvents): Site
    {
        $rawKey = $this->extractSiteKey($rawEvents[0]);
        if ($rawKey === null) {
            throw InvalidTrackerKey::malformed();
        }

        $site = $this->sites->findByKey(SiteKey::fromString($rawKey));
        if (!$site instanceof Site) {
            throw InvalidTrackerKey::unknown();
        }

        return $site;
    }

    private function enforceRateLimit(Site $site, int $cost): void
    {
        $decision = $this->rateLimiter->consume($site->id, $cost);
        if (!$decision->allowed) {
            throw RateLimitExceeded::retryAfter($decision->retryAfterSeconds);
        }
    }

    /**
     * @param list<array<string, mixed>> $rawEvents
     */
    private function processEvents(array $rawEvents, RequestContext $context, Site $site): IngestionResult
    {
        $isBot = $site->botFilteringEnabled && $this->botDetector->isBot($context);
        $excludedByGeo = $this->isExcludedIp($site, $context->ipAddress);

        $results = [];
        $buffered = [];

        foreach ($rawEvents as $index => $rawEvent) {
            $result = $this->processSingle($rawEvent, $context, $site, $index, $isBot || $excludedByGeo, $buffered);
            $results[] = $result;
        }

        if ($buffered !== []) {
            $this->buffer->append($buffered);
        }

        return new IngestionResult($results);
    }

    /**
     * @param array<string, mixed> $rawEvent
     * @param list<BufferedEvent>  $buffered passed by reference; valid events are appended
     */
    private function processSingle(
        array $rawEvent,
        RequestContext $context,
        Site $site,
        int $index,
        bool $silentlyDrop,
        array &$buffered,
    ): EventResult {
        try {
            $canonical = $this->eventFactory->fromCanonical($this->normalizer->normalize($rawEvent));
        } catch (EventValidationFailed $e) {
            return EventResult::rejected($index, $e->slug(), $e->violations());
        }

        if (!$this->keyMatches($canonical, $site)) {
            return EventResult::rejected($index, 'invalid-tracker-key');
        }

        if (!$this->isCustomEventAllowed($canonical, $site)) {
            return EventResult::rejected($index, 'validation-failed');
        }

        // Bot / excluded traffic and idempotent replays are reported as accepted
        // (204 semantics) but never buffered.
        if ($silentlyDrop) {
            return EventResult::accepted($index);
        }

        if (!$this->idempotency->registerIfFirstSeen($site->id, $canonical->eventId)) {
            return EventResult::accepted($index);
        }

        $buffered[] = new BufferedEvent($site->id, $canonical, $context);

        return EventResult::accepted($index);
    }

    private function keyMatches(CanonicalEvent $event, Site $site): bool
    {
        return hash_equals($site->trackerKey, $event->siteKey);
    }

    /**
     * Enforces site_settings.custom_event_names (postgres-schema.sql §5): a null
     * allowlist allows any custom event name; a configured list restricts which
     * `custom` events are accepted. Non-custom events are never gated here.
     */
    private function isCustomEventAllowed(CanonicalEvent $event, Site $site): bool
    {
        if ($site->customEventNames === null || $event->eventName->value !== 'custom') {
            return true;
        }

        $name = $event->customProperties['event_name'] ?? '';

        return is_string($name) && in_array($name, $site->customEventNames, true);
    }

    private function isExcludedIp(Site $site, string $ipAddress): bool
    {
        return in_array($ipAddress, $site->excludedIps, true);
    }

    /**
     * @param array<string, mixed> $rawEvent
     */
    private function extractSiteKey(array $rawEvent): ?string
    {
        $key = $rawEvent['site_key'] ?? $rawEvent['k'] ?? null;

        return is_string($key) ? $key : null;
    }
}
