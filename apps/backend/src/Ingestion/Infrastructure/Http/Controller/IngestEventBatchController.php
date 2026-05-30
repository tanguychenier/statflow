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

namespace App\Ingestion\Infrastructure\Http\Controller;

use App\Ingestion\Application\Command\IngestEventsCommand;
use App\Ingestion\Application\Handler\IngestEventsHandler;
use App\Ingestion\Domain\Exception\IngestionException;
use App\Ingestion\Infrastructure\Http\Support\IngestionProblemFactory;
use App\Ingestion\Infrastructure\Http\Support\IngestionRequestParser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/v1/events/batch — ingest the unified batch envelope
 * (openapi.yaml `ingestEventBatch`).
 *
 * Returns 204 when every event was accepted, or 200 with a BatchResultResponse
 * body listing per-item status when some events were rejected. Request-level
 * failures (auth, rate limit, oversize) still surface as Problem Details with
 * their own status, never as a 200 partial result.
 */
#[Route('/api/v1/events/batch', name: 'ingestion_event_batch', methods: ['POST'])]
final readonly class IngestEventBatchController
{
    public function __construct(
        private IngestEventsHandler $handler,
        private IngestionRequestParser $parser,
        private IngestionProblemFactory $problems,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        try {
            $events = $this->parser->parseEvents($request, isBatch: true);
            $result = ($this->handler)(new IngestEventsCommand(
                rawEvents: $events,
                context: $this->parser->buildContext($request),
                isBatch: true,
            ));
        } catch (IngestionException $exception) {
            return $this->problems->fromException($exception, $request->getRequestUri());
        }

        if ($result->allAccepted()) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse($result->toArray(), Response::HTTP_OK);
    }
}
