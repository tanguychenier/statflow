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

use App\Analytics\Application\Segment\CreateSegmentHandler;
use App\Analytics\Application\Segment\SegmentRequestFactory;
use App\Analytics\Infrastructure\Http\Support\AnalyticsProblemFactory;
use App\Analytics\Infrastructure\Http\Support\AnalyticsRequestParser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * POST /api/v1/analytics/{site_id}/segments (openapi.yaml `createSegment`).
 *
 * The creator's user id is read from the `user_id` request attribute set by the
 * authentication layer; it is nullable so the endpoint still works for API-key
 * callers that have no user identity.
 */
#[Route(
    '/api/v1/analytics/{site_id}/segments',
    name: 'analytics_segments_create',
    methods: ['POST'],
)]
final readonly class CreateSegmentController
{
    public function __construct(
        private CreateSegmentHandler $handler,
        private SegmentRequestFactory $factory,
        private AnalyticsRequestParser $parser,
        private AnalyticsProblemFactory $problems,
    ) {
    }

    public function __invoke(Request $request, string $site_id): JsonResponse
    {
        try {
            $siteId = $this->parser->siteId($site_id);
            $body = $this->parser->decodeBody($request->getContent());
            $createdBy = $this->currentUserId($request);
            $command = $this->factory->createCommand($siteId, $body, $createdBy);

            return new JsonResponse(($this->handler)($command), Response::HTTP_CREATED);
        } catch (Throwable $exception) {
            return $this->problems->fromThrowable($exception, $request->getRequestUri());
        }
    }

    private function currentUserId(Request $request): ?string
    {
        $userId = $request->attributes->get('user_id');

        return is_string($userId) && $userId !== '' ? $userId : null;
    }
}
