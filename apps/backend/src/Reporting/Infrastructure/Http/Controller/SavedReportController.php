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

use App\Reporting\Application\Command\CreateSavedReportCommand;
use App\Reporting\Application\Command\DeleteSavedReportCommand;
use App\Reporting\Application\Dto\PaginatedView;
use App\Reporting\Application\Dto\SavedReportView;
use App\Reporting\Application\Query\GetSavedReportQuery;
use App\Reporting\Application\Query\ListSavedReportsQuery;
use App\Reporting\Domain\Port\ListCriteria;
use App\Reporting\Infrastructure\Http\ActingUserResolver;
use App\Reporting\Infrastructure\Http\BusDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Saved-report endpoints for a site.
 *
 * @see openapi listSavedReports, createSavedReport, getSavedReport, deleteSavedReport
 */
final readonly class SavedReportController
{
    use BusDispatcher;

    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private ActingUserResolver $actingUser,
    ) {
    }

    #[Route('/api/v1/sites/{siteId}/reports', name: 'api_v1_reports_list', methods: ['GET'])]
    public function list(string $siteId, Request $request): JsonResponse
    {
        $query = new ListSavedReportsQuery(
            actingUserId: $this->actingUser->userId(),
            siteId: $siteId,
            cursor: $request->query->get('cursor') !== null ? (string) $request->query->get('cursor') : null,
            limit: $request->query->getInt('limit', ListCriteria::DEFAULT_LIMIT),
        );

        /** @var PaginatedView $result */
        $result = $this->handle($this->queryBus, $query);

        return new JsonResponse($result->toArray(), Response::HTTP_OK);
    }

    #[Route('/api/v1/sites/{siteId}/reports', name: 'api_v1_reports_create', methods: ['POST'])]
    public function create(string $siteId, Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $command = new CreateSavedReportCommand(
            actingUserId: $this->actingUser->userId(),
            siteId: $siteId,
            name: $this->requireString($body, 'name'),
            description: $this->optionalString($body, 'description'),
            reportType: $this->requireString($body, 'report_type'),
            query: $this->requireObject($body, 'query'),
        );

        /** @var SavedReportView $result */
        $result = $this->handle($this->commandBus, $command);

        return new JsonResponse($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/api/v1/sites/{siteId}/reports/{reportId}', name: 'api_v1_reports_get', methods: ['GET'])]
    public function get(string $siteId, string $reportId): JsonResponse
    {
        $query = new GetSavedReportQuery($this->actingUser->userId(), $siteId, $reportId);

        /** @var SavedReportView $result */
        $result = $this->handle($this->queryBus, $query);

        return new JsonResponse($result->toArray(), Response::HTTP_OK);
    }

    #[Route('/api/v1/sites/{siteId}/reports/{reportId}', name: 'api_v1_reports_delete', methods: ['DELETE'])]
    public function delete(string $siteId, string $reportId): JsonResponse
    {
        $command = new DeleteSavedReportCommand($this->actingUser->userId(), $siteId, $reportId);

        $this->handle($this->commandBus, $command);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
