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

namespace App\Reporting\Infrastructure\Http\Controller;

use App\Reporting\Application\Command\CreateExportCommand;
use App\Reporting\Application\Dto\ExportView;
use App\Reporting\Application\Query\GetExportQuery;
use App\Reporting\Infrastructure\Http\ActingUserResolver;
use App\Reporting\Infrastructure\Http\BusDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Asynchronous data-export endpoints for a site: request (POST -> 202) and poll
 * (GET).
 *
 * @see openapi createDataExport, getExportStatus
 */
final readonly class ExportController
{
    use BusDispatcher;

    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private ActingUserResolver $actingUser,
    ) {
    }

    #[Route('/api/v1/sites/{siteId}/exports', name: 'api_v1_exports_create', methods: ['POST'])]
    public function create(string $siteId, Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $command = new CreateExportCommand(
            actingUserId: $this->actingUser->userId(),
            siteId: $siteId,
            format: $this->requireString($body, 'format'),
            query: $this->requireObject($body, 'query'),
            notifyEmail: $this->optionalString($body, 'notify_email'),
        );

        /** @var ExportView $result */
        $result = $this->handle($this->commandBus, $command);

        return new JsonResponse($result->toArray(), Response::HTTP_ACCEPTED);
    }

    #[Route('/api/v1/sites/{siteId}/exports/{exportId}', name: 'api_v1_exports_get', methods: ['GET'])]
    public function get(string $siteId, string $exportId): JsonResponse
    {
        $query = new GetExportQuery($this->actingUser->userId(), $siteId, $exportId);

        /** @var ExportView $result */
        $result = $this->handle($this->queryBus, $query);

        return new JsonResponse($result->toArray(), Response::HTTP_OK);
    }
}
