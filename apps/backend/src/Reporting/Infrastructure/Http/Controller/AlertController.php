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

use App\Reporting\Application\Command\CreateAlertCommand;
use App\Reporting\Application\Command\DeleteAlertCommand;
use App\Reporting\Application\Command\UpdateAlertCommand;
use App\Reporting\Application\Dto\AlertView;
use App\Reporting\Application\Dto\PaginatedView;
use App\Reporting\Application\Query\ListAlertsQuery;
use App\Reporting\Domain\Port\ListCriteria;
use App\Reporting\Infrastructure\Http\ActingUserResolver;
use App\Reporting\Infrastructure\Http\BusDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Metric-alert endpoints for a site.
 *
 * @see openapi listAlerts, createAlert, updateAlert, deleteAlert
 */
final readonly class AlertController
{
    use BusDispatcher;

    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private ActingUserResolver $actingUser,
    ) {
    }

    #[Route('/api/v1/sites/{siteId}/alerts', name: 'api_v1_alerts_list', methods: ['GET'])]
    public function list(string $siteId, Request $request): JsonResponse
    {
        $query = new ListAlertsQuery(
            actingUserId: $this->actingUser->userId(),
            siteId: $siteId,
            cursor: $request->query->get('cursor') !== null ? (string) $request->query->get('cursor') : null,
            limit: $request->query->getInt('limit', ListCriteria::DEFAULT_LIMIT),
        );

        /** @var PaginatedView $result */
        $result = $this->handle($this->queryBus, $query);

        return new JsonResponse($result->toArray(), Response::HTTP_OK);
    }

    #[Route('/api/v1/sites/{siteId}/alerts', name: 'api_v1_alerts_create', methods: ['POST'])]
    public function create(string $siteId, Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $filtersRaw = $this->optionalList($body, 'filters') ?? [];

        $command = new CreateAlertCommand(
            actingUserId: $this->actingUser->userId(),
            siteId: $siteId,
            name: $this->requireString($body, 'name'),
            metric: $this->requireString($body, 'metric'),
            condition: $this->requireString($body, 'condition'),
            threshold: $this->requireFloat($body, 'threshold'),
            comparisonPeriod: $this->optionalString($body, 'comparison_period'),
            filters: $this->objectList($filtersRaw, 'filters'),
            notificationChannels: $this->objectList($this->requireList($body, 'notification_channels'), 'notification_channels'),
        );

        /** @var AlertView $result */
        $result = $this->handle($this->commandBus, $command);

        return new JsonResponse($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/api/v1/sites/{siteId}/alerts/{alertId}', name: 'api_v1_alerts_update', methods: ['PATCH'])]
    public function update(string $siteId, string $alertId, Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $channelsRaw = $this->optionalList($body, 'notification_channels');

        $command = new UpdateAlertCommand(
            actingUserId: $this->actingUser->userId(),
            siteId: $siteId,
            alertId: $alertId,
            name: $this->optionalString($body, 'name'),
            condition: $this->optionalString($body, 'condition'),
            threshold: $this->optionalFloat($body, 'threshold'),
            isActive: $this->optionalBool($body, 'is_active'),
            notificationChannels: $channelsRaw !== null ? $this->objectList($channelsRaw, 'notification_channels') : null,
        );

        /** @var AlertView $result */
        $result = $this->handle($this->commandBus, $command);

        return new JsonResponse($result->toArray(), Response::HTTP_OK);
    }

    #[Route('/api/v1/sites/{siteId}/alerts/{alertId}', name: 'api_v1_alerts_delete', methods: ['DELETE'])]
    public function delete(string $siteId, string $alertId): JsonResponse
    {
        $command = new DeleteAlertCommand($this->actingUser->userId(), $siteId, $alertId);

        $this->handle($this->commandBus, $command);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
