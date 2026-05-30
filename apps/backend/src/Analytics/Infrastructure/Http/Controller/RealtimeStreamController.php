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

namespace App\Analytics\Infrastructure\Http\Controller;

use App\Analytics\Application\Query\RealtimeEventsHandler;
use App\Analytics\Application\Query\RealtimeHandler;
use App\Analytics\Infrastructure\Http\Sse\SseWriter;
use App\Analytics\Infrastructure\Http\Support\AnalyticsProblemFactory;
use App\Analytics\Infrastructure\Http\Support\AnalyticsRequestParser;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * GET /api/v1/analytics/{site_id}/realtime/stream (openapi.yaml `streamRealtime`).
 *
 * A Server-Sent Events connection backing the Realtime screen. It pushes a
 * `stats` RealtimeResponse on a fixed cadence and an `event` RealtimeEvent for
 * each newly ingested event (newest first), over the canonical 5-minute window.
 *
 * The stream is intentionally short-lived (bounded tick count): the SPA passes
 * a fresh access token on reconnect, so capping the lifetime keeps a long-lived
 * worker from holding a stale token (openapi.yaml streamRealtime security note).
 */
#[Route(
    '/api/v1/analytics/{site_id}/realtime/stream',
    name: 'analytics_realtime_stream',
    methods: ['GET'],
)]
final readonly class RealtimeStreamController
{
    private const STATS_INTERVAL_SECONDS = 2;

    private const MAX_TICKS = 30;

    public function __construct(
        private RealtimeHandler $stats,
        private RealtimeEventsHandler $events,
        private AnalyticsRequestParser $parser,
        private AnalyticsProblemFactory $problems,
        private SseWriter $writer,
    ) {
    }

    public function __invoke(string $site_id): Response
    {
        try {
            $siteId = $this->parser->siteId($site_id);
        } catch (Throwable $exception) {
            return $this->problems->fromThrowable($exception);
        }

        $response = new StreamedResponse(fn () => $this->stream($siteId));
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }

    private function stream(Uuid $siteId): void
    {
        $watermark = null;

        for ($tick = 0; $tick < self::MAX_TICKS; ++$tick) {
            $this->writer->emit('stats', ($this->stats)($siteId));

            $events = ($this->events)($siteId, $watermark);
            // Advance the watermark on ingested_at (server clock) — the column the
            // handler filters on — so each event is pushed exactly once.
            if ($events !== []) {
                $watermark = $events[0]['ingested_at'];
            }
            foreach (array_reverse($events) as $event) {
                unset($event['ingested_at']);
                $this->writer->emit('event', $event);
            }

            $this->writer->flush();
            $this->writer->sleep(self::STATS_INTERVAL_SECONDS);
        }
    }
}
