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
use App\Ingestion\Application\Dto\EventResult;
use App\Ingestion\Application\Handler\IngestEventsHandler;
use App\Ingestion\Domain\Exception\IngestionException;
use App\Ingestion\Infrastructure\Http\Support\IngestionProblemFactory;
use App\Ingestion\Infrastructure\Http\Support\IngestionRequestParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/v1/events — ingest a single event (openapi.yaml `ingestEvent`).
 *
 * Returns 204 on success (including idempotent replays); a Problem Details body
 * with the relevant status on failure. The endpoint accepts a single canonical
 * event or a one-entry batch envelope (event-contract.md §3).
 */
#[Route('/api/v1/events', name: 'ingestion_event', methods: ['POST'])]
final readonly class IngestEventController
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
            $events = $this->parser->parseEvents($request, isBatch: false);
            $result = ($this->handler)(new IngestEventsCommand(
                rawEvents: $events,
                context: $this->parser->buildContext($request),
                isBatch: false,
            ));
        } catch (IngestionException $exception) {
            return $this->problems->fromException($exception, $request->getRequestUri());
        }

        $rejection = $result->firstRejection();
        if ($rejection instanceof EventResult) {
            return $this->problems->fromRejection($rejection, $request->getRequestUri());
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
