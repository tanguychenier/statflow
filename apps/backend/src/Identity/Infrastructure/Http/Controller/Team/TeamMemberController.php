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

use App\Identity\Application\Command\ChangeMemberRoleCommand;
use App\Identity\Application\Command\InviteTeamMemberCommand;
use App\Identity\Application\Command\RemoveTeamMemberCommand;
use App\Identity\Application\DTO\TeamMemberView;
use App\Identity\Application\Query\ListTeamMembersQuery;
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
 * Team membership endpoints: list members, invite a member, change a member's
 * role, and remove a member. Mutations require owner/admin (openapi.yaml Teams).
 */
final readonly class TeamMemberController
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

    #[Route('/api/v1/teams/{team_id}/members', name: 'api_v1_team_members_list', methods: ['GET'])]
    public function list(string $team_id, Request $request): JsonResponse
    {
        /** @var list<TeamMemberView> $members */
        $members = $this->handle(
            $this->queryBus,
            new ListTeamMembersQuery($this->actingUser->userId(), $team_id),
        );

        $limit = $request->query->getInt('limit', self::DEFAULT_LIMIT);
        $data = array_map(static fn (TeamMemberView $m): array => $m->toArray(), $members);

        return ApiResponse::json(PaginationEnvelope::singlePage($data, $limit));
    }

    #[Route('/api/v1/teams/{team_id}/members', name: 'api_v1_team_members_invite', methods: ['POST'])]
    public function invite(string $team_id, Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $command = new InviteTeamMemberCommand(
            actorId: $this->actingUser->userId(),
            teamId: $team_id,
            email: $this->stringField($body, 'email'),
            role: $this->stringField($body, 'role'),
            auditContext: $this->auditContext->fromRequest($request),
        );

        /** @var TeamMemberView $member */
        $member = $this->handle($this->commandBus, $command);

        return ApiResponse::json($member->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/api/v1/teams/{team_id}/members/{user_id}', name: 'api_v1_team_members_update', methods: ['PATCH'])]
    public function updateRole(string $team_id, string $user_id, Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $command = new ChangeMemberRoleCommand(
            actorId: $this->actingUser->userId(),
            teamId: $team_id,
            userId: $user_id,
            role: $this->stringField($body, 'role'),
            auditContext: $this->auditContext->fromRequest($request),
        );

        /** @var TeamMemberView $member */
        $member = $this->handle($this->commandBus, $command);

        return ApiResponse::json($member->toArray());
    }

    #[Route('/api/v1/teams/{team_id}/members/{user_id}', name: 'api_v1_team_members_remove', methods: ['DELETE'])]
    public function remove(string $team_id, string $user_id, Request $request): Response
    {
        $command = new RemoveTeamMemberCommand(
            actorId: $this->actingUser->userId(),
            teamId: $team_id,
            userId: $user_id,
            auditContext: $this->auditContext->fromRequest($request),
        );

        $this->handle($this->commandBus, $command);

        return ApiResponse::noContent();
    }
}
