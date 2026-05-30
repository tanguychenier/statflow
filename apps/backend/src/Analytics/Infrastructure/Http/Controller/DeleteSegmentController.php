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

use App\Analytics\Application\Segment\DeleteSegmentHandler;
use App\Analytics\Infrastructure\Http\Support\AnalyticsProblemFactory;
use App\Analytics\Infrastructure\Http\Support\AnalyticsRequestParser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * DELETE /api/v1/analytics/{site_id}/segments/{segment_id} (openapi.yaml `deleteSegment`).
 */
#[Route(
    '/api/v1/analytics/{site_id}/segments/{segment_id}',
    name: 'analytics_segments_delete',
    methods: ['DELETE'],
)]
final readonly class DeleteSegmentController
{
    public function __construct(
        private DeleteSegmentHandler $handler,
        private AnalyticsRequestParser $parser,
        private AnalyticsProblemFactory $problems,
    ) {
    }

    public function __invoke(string $site_id, string $segment_id): Response
    {
        try {
            $siteId = $this->parser->siteId($site_id);
            $segmentId = $this->parser->siteId($segment_id);
            ($this->handler)($siteId, $segmentId);

            return new Response(null, Response::HTTP_NO_CONTENT);
        } catch (Throwable $exception) {
            return $this->problems->fromThrowable($exception);
        }
    }
}
