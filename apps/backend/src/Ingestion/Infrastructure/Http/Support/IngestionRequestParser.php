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

namespace App\Ingestion\Infrastructure\Http\Support;

use App\Ingestion\Domain\Exception\MalformedRequest;
use App\Ingestion\Domain\Exception\PayloadTooLarge;
use App\Ingestion\Domain\Model\RequestContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Turns a raw ingestion HTTP request into the list of raw event objects plus the
 * request context, enforcing the transport rules of event-contract.md §3:
 *
 *  - body is JSON regardless of Content-Type (text/plain on the tracker paths);
 *  - the body is a single event OR the `events` envelope;
 *  - size budget: 16 KB single, 256 KB / 100 events batch;
 *  - the body MAY be gzip-encoded by server-side integrations (ADR-0007 §7).
 *
 * The parser deliberately does not look at field semantics — that is the
 * domain's job; it only shapes the transport into the command's inputs.
 */
final class IngestionRequestParser
{
    private const SINGLE_LIMIT_BYTES = 16 * 1024;

    private const BATCH_LIMIT_BYTES = 256 * 1024;

    private const MAX_BATCH_EVENTS = 100;

    /**
     * @return list<array<string, mixed>>
     */
    public function parseEvents(Request $request, bool $isBatch): array
    {
        $body = $this->readBody($request);

        $limit = $isBatch ? self::BATCH_LIMIT_BYTES : self::SINGLE_LIMIT_BYTES;
        if (strlen($body) > $limit) {
            throw $isBatch ? PayloadTooLarge::batch($limit) : PayloadTooLarge::single($limit);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw $decoded === null && $body !== 'null'
                ? MalformedRequest::notJson()
                : MalformedRequest::notAnObject();
        }

        $events = $this->extractEvents($decoded);

        if (count($events) > self::MAX_BATCH_EVENTS) {
            throw PayloadTooLarge::tooManyEvents(self::MAX_BATCH_EVENTS);
        }

        return $events;
    }

    public function buildContext(Request $request): RequestContext
    {
        return new RequestContext(
            ipAddress: $request->getClientIp() ?? '',
            userAgent: (string) $request->headers->get('User-Agent', ''),
            acceptLanguage: (string) $request->headers->get('Accept-Language', ''),
            origin: $request->headers->get('Origin'),
        );
    }

    private function readBody(Request $request): string
    {
        $content = $request->getContent();

        if (strtolower((string) $request->headers->get('Content-Encoding', '')) === 'gzip') {
            $decoded = @gzdecode($content);
            if ($decoded === false) {
                throw MalformedRequest::reason('Request body declared gzip but could not be decoded.');
            }
            $content = $decoded;
        }

        if (trim($content) === '') {
            throw MalformedRequest::notJson();
        }

        return $content;
    }

    /**
     * Accepts the unified envelope (root key `events`) or a single event object
     * (event-contract.md §3). A single event is wrapped into a one-element list.
     *
     * @param array<int|string, mixed> $decoded
     *
     * @return list<array<string, mixed>>
     */
    private function extractEvents(array $decoded): array
    {
        if (array_key_exists('events', $decoded)) {
            if (!is_array($decoded['events']) || !array_is_list($decoded['events'])) {
                throw MalformedRequest::reason('The "events" envelope key must be an array.');
            }

            $events = [];
            foreach ($decoded['events'] as $event) {
                if (!is_array($event)) {
                    throw MalformedRequest::reason('Each batch entry must be an object.');
                }
                /** @var array<string, mixed> $event */
                $events[] = $event;
            }

            if ($events === []) {
                throw MalformedRequest::emptyBatch();
            }

            return $events;
        }

        /** @var array<string, mixed> $decoded */
        return [$decoded];
    }
}
