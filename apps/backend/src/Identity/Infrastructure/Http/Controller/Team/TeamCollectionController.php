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

use App\Identity\Application\Command\CreateTeamCommand;
use App\Identity\Application\DTO\TeamView;
use App\Identity\Application\Query\ListUserTeamsQuery;
use App\Identity\Infrastructure\Http\ActingUserResolver;
use App\Identity\Infrastructure\Http\AuditContextFactory;
use App\Identity\Infrastructure\Http\BusDispatcher;
use App\Identity\Infrastructure\Http\PaginationEnvelope;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Collection endpoints for teams: list the caller's teams (GET) and create a new
 * team (POST). The creator becomes the founding owner. (openapi.yaml Teams)
 */
final readonly class TeamCollectionController
{
    use BusDispatcher;

    private const DEFAULT_LIMIT = 20;

    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private ActingUserResolver $actingUser,
        private AuditContextFactory $auditContext,
    ) {
    }

    #[Route('/api/v1/teams', name: 'api_v1_teams_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var list<TeamView> $teams */
        $teams = $this->handle($this->queryBus, new ListUserTeamsQuery($this->actingUser->userId()));

        $limit = $request->query->getInt('limit', self::DEFAULT_LIMIT);
        $data = array_map(static fn (TeamView $t): array => $t->toArray(), $teams);

        return ApiResponse::json(PaginationEnvelope::singlePage($data, $limit));
    }

    #[Route('/api/v1/teams', name: 'api_v1_teams_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $command = new CreateTeamCommand(
            ownerId: $this->actingUser->userId(),
            name: $this->stringField($body, 'name'),
            auditContext: $this->auditContext->fromRequest($request),
        );

        /** @var TeamView $team */
        $team = $this->handle($this->commandBus, $command);

        return ApiResponse::json($team->toArray(), Response::HTTP_CREATED);
    }
}
