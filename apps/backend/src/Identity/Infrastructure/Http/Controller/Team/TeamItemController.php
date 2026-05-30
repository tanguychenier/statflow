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

namespace App\Identity\Infrastructure\Http\Controller\Team;

use App\Identity\Application\Command\DeleteTeamCommand;
use App\Identity\Application\Command\UpdateTeamCommand;
use App\Identity\Application\DTO\TeamView;
use App\Identity\Application\Query\GetTeamQuery;
use App\Identity\Infrastructure\Http\ActingUserResolver;
use App\Identity\Infrastructure\Http\AuditContextFactory;
use App\Identity\Infrastructure\Http\BusDispatcher;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Single-team endpoints: read (GET), rename (PATCH, owner/admin), delete (DELETE,
 * owner only). (openapi.yaml Teams)
 */
final readonly class TeamItemController
{
    use BusDispatcher;

    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private ActingUserResolver $actingUser,
        private AuditContextFactory $auditContext,
    ) {
    }

    #[Route('/api/v1/teams/{team_id}', name: 'api_v1_teams_get', methods: ['GET'])]
    public function get(string $team_id): JsonResponse
    {
        /** @var TeamView $team */
        $team = $this->handle($this->queryBus, new GetTeamQuery($this->actingUser->userId(), $team_id));

        return ApiResponse::json($team->toArray());
    }

    #[Route('/api/v1/teams/{team_id}', name: 'api_v1_teams_update', methods: ['PATCH'])]
    public function update(string $team_id, Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $command = new UpdateTeamCommand(
            actorId: $this->actingUser->userId(),
            teamId: $team_id,
            name: $this->nullableStringField($body, 'name'),
            auditContext: $this->auditContext->fromRequest($request),
        );

        /** @var TeamView $team */
        $team = $this->handle($this->commandBus, $command);

        return ApiResponse::json($team->toArray());
    }

    #[Route('/api/v1/teams/{team_id}', name: 'api_v1_teams_delete', methods: ['DELETE'])]
    public function delete(string $team_id, Request $request): Response
    {
        $command = new DeleteTeamCommand(
            actorId: $this->actingUser->userId(),
            teamId: $team_id,
            auditContext: $this->auditContext->fromRequest($request),
        );

        $this->handle($this->commandBus, $command);

        return ApiResponse::noContent();
    }
}
