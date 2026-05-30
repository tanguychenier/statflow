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

use App\Analytics\Application\Query\AnalyticsQueryFactory;
use App\Analytics\Application\Query\RetentionHandler;
use App\Analytics\Infrastructure\Http\Support\AnalyticsProblemFactory;
use App\Analytics\Infrastructure\Http\Support\AnalyticsRequestParser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * POST /api/v1/analytics/{site_id}/retention (openapi.yaml `queryRetention`).
 */
#[Route(
    '/api/v1/analytics/{site_id}/retention',
    name: 'analytics_retention',
    methods: ['POST'],
)]
final readonly class RetentionController
{
    public function __construct(
        private RetentionHandler $handler,
        private AnalyticsQueryFactory $factory,
        private AnalyticsRequestParser $parser,
        private AnalyticsProblemFactory $problems,
    ) {
    }

    public function __invoke(Request $request, string $site_id): JsonResponse
    {
        try {
            $siteId = $this->parser->siteId($site_id);
            $body = $this->parser->decodeBody($request->getContent());
            $query = $this->factory->retentionQuery($siteId, $body);

            return new JsonResponse(($this->handler)($query), Response::HTTP_OK);
        } catch (Throwable $exception) {
            return $this->problems->fromThrowable($exception, $request->getRequestUri());
        }
    }
}
