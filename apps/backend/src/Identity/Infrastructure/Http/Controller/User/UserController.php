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

namespace App\Identity\Infrastructure\Http\Controller\User;

use App\Identity\Application\Command\ChangePasswordCommand;
use App\Identity\Application\Command\UpdateUserProfileCommand;
use App\Identity\Application\DTO\UserView;
use App\Identity\Application\Query\GetUserProfileQuery;
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
 * Current-user account endpoints (openapi.yaml Users): read/update the profile
 * and change the password. All require an authenticated session.
 */
final readonly class UserController
{
    use BusDispatcher;

    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private ActingUserResolver $actingUser,
        private AuditContextFactory $auditContext,
    ) {
    }

    #[Route('/api/v1/users/me', name: 'api_v1_users_me_get', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        /** @var UserView $user */
        $user = $this->handle($this->queryBus, new GetUserProfileQuery($this->actingUser->userId()));

        return ApiResponse::json($user->toArray());
    }

    #[Route('/api/v1/users/me', name: 'api_v1_users_me_update', methods: ['PATCH'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $command = new UpdateUserProfileCommand(
            userId: $this->actingUser->userId(),
            name: $this->nullableStringField($body, 'name'),
            email: $this->nullableStringField($body, 'email'),
            auditContext: $this->auditContext->fromRequest($request),
        );

        /** @var UserView $user */
        $user = $this->handle($this->commandBus, $command);

        return ApiResponse::json($user->toArray());
    }

    #[Route('/api/v1/users/me/password', name: 'api_v1_users_me_password', methods: ['PUT'])]
    public function changePassword(Request $request): Response
    {
        $body = $this->decodeBody($request);

        $command = new ChangePasswordCommand(
            userId: $this->actingUser->userId(),
            currentPassword: $this->stringField($body, 'current_password'),
            newPassword: $this->stringField($body, 'new_password'),
            auditContext: $this->auditContext->fromRequest($request),
        );

        $this->handle($this->commandBus, $command);

        return ApiResponse::noContent();
    }
}
