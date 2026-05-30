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

use App\Reporting\Application\Command\CreateScheduledReportCommand;
use App\Reporting\Application\Command\DeleteScheduledReportCommand;
use App\Reporting\Application\Command\UpdateScheduledReportCommand;
use App\Reporting\Application\Dto\PaginatedView;
use App\Reporting\Application\Dto\ScheduledReportView;
use App\Reporting\Application\Query\ListScheduledReportsQuery;
use App\Reporting\Domain\Port\ListCriteria;
use App\Reporting\Infrastructure\Http\ActingUserResolver;
use App\Reporting\Infrastructure\Http\BusDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Scheduled email-report endpoints for a site.
 *
 * @see openapi listScheduledReports, createScheduledReport, updateScheduledReport, deleteScheduledReport
 */
final readonly class ScheduledReportController
{
    use BusDispatcher;

    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private ActingUserResolver $actingUser,
    ) {
    }

    #[Route('/api/v1/sites/{siteId}/scheduled-reports', name: 'api_v1_scheduled_reports_list', methods: ['GET'])]
    public function list(string $siteId, Request $request): JsonResponse
    {
        $query = new ListScheduledReportsQuery(
            actingUserId: $this->actingUser->userId(),
            siteId: $siteId,
            cursor: $request->query->get('cursor') !== null ? (string) $request->query->get('cursor') : null,
            limit: $request->query->getInt('limit', ListCriteria::DEFAULT_LIMIT),
        );

        /** @var PaginatedView $result */
        $result = $this->handle($this->queryBus, $query);

        return new JsonResponse($result->toArray(), Response::HTTP_OK);
    }

    #[Route('/api/v1/sites/{siteId}/scheduled-reports', name: 'api_v1_scheduled_reports_create', methods: ['POST'])]
    public function create(string $siteId, Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $command = new CreateScheduledReportCommand(
            actingUserId: $this->actingUser->userId(),
            siteId: $siteId,
            name: $this->requireString($body, 'name'),
            savedReportId: $this->optionalString($body, 'saved_report_id'),
            recipients: $this->stringList($this->requireList($body, 'recipients'), 'recipients'),
            scheduleCron: $this->requireString($body, 'schedule_cron'),
            timezone: $this->requireString($body, 'timezone'),
        );

        /** @var ScheduledReportView $result */
        $result = $this->handle($this->commandBus, $command);

        return new JsonResponse($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/api/v1/sites/{siteId}/scheduled-reports/{scheduledReportId}', name: 'api_v1_scheduled_reports_update', methods: ['PATCH'])]
    public function update(string $siteId, string $scheduledReportId, Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $recipientsRaw = $this->optionalList($body, 'recipients');

        $command = new UpdateScheduledReportCommand(
            actingUserId: $this->actingUser->userId(),
            siteId: $siteId,
            scheduledReportId: $scheduledReportId,
            name: $this->optionalString($body, 'name'),
            recipients: $recipientsRaw !== null ? $this->stringList($recipientsRaw, 'recipients') : null,
            scheduleCron: $this->optionalString($body, 'schedule_cron'),
            timezone: $this->optionalString($body, 'timezone'),
            isActive: $this->optionalBool($body, 'is_active'),
        );

        /** @var ScheduledReportView $result */
        $result = $this->handle($this->commandBus, $command);

        return new JsonResponse($result->toArray(), Response::HTTP_OK);
    }

    #[Route('/api/v1/sites/{siteId}/scheduled-reports/{scheduledReportId}', name: 'api_v1_scheduled_reports_delete', methods: ['DELETE'])]
    public function delete(string $siteId, string $scheduledReportId): JsonResponse
    {
        $command = new DeleteScheduledReportCommand($this->actingUser->userId(), $siteId, $scheduledReportId);

        $this->handle($this->commandBus, $command);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
