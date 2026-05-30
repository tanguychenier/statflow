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

use App\Analytics\Application\Query\RealtimeHandler;
use App\Analytics\Infrastructure\Http\Support\AnalyticsProblemFactory;
use App\Analytics\Infrastructure\Http\Support\AnalyticsRequestParser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * GET /api/v1/analytics/{site_id}/realtime (openapi.yaml `getRealtimeStats`).
 */
#[Route(
    '/api/v1/analytics/{site_id}/realtime',
    name: 'analytics_realtime',
    methods: ['GET'],
)]
final readonly class RealtimeController
{
    public function __construct(
        private RealtimeHandler $handler,
        private AnalyticsRequestParser $parser,
        private AnalyticsProblemFactory $problems,
    ) {
    }

    public function __invoke(string $site_id): JsonResponse
    {
        try {
            $siteId = $this->parser->siteId($site_id);

            return new JsonResponse(($this->handler)($siteId), Response::HTTP_OK);
        } catch (Throwable $exception) {
            return $this->problems->fromThrowable($exception);
        }
    }
}
