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

namespace App\Identity\Infrastructure\Http\Controller\Auth;

use App\Identity\Application\Command\LoginCommand;
use App\Identity\Application\Command\LogoutCommand;
use App\Identity\Application\Command\RefreshSessionCommand;
use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\Command\RequestPasswordResetCommand;
use App\Identity\Application\Command\ResetPasswordCommand;
use App\Identity\Application\DTO\AuthenticationResult;
use App\Identity\Application\DTO\UserView;
use App\Identity\Infrastructure\Http\AuditContextFactory;
use App\Identity\Infrastructure\Http\BusDispatcher;
use App\Identity\Infrastructure\Http\RefreshCookieFactory;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Session-based email + password auth endpoints (ADR-0009, openapi.yaml Auth):
 * login, register, forgot/reset password, refresh, and logout. Access tokens are
 * returned in the body; the rotating refresh token rides the HttpOnly sf_rt
 * cookie.
 */
final readonly class AuthController
{
    use BusDispatcher;

    public function __construct(
        private MessageBusInterface $commandBus,
        private AuditContextFactory $auditContext,
        private RefreshCookieFactory $refreshCookie,
    ) {
    }

    #[Route('/api/v1/auth/login', name: 'api_v1_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $command = new LoginCommand(
            email: $this->stringField($body, 'email'),
            password: $this->stringField($body, 'password'),
            auditContext: $this->auditContext->anonymous($request, $this->nullableStringField($body, 'email')),
        );

        /** @var AuthenticationResult $result */
        $result = $this->handle($this->commandBus, $command);

        return $this->withRefreshCookie(
            ApiResponse::json($result->toTokenResponse(), Response::HTTP_OK),
            $result,
        );
    }

    #[Route('/api/v1/auth/register', name: 'api_v1_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $command = new RegisterUserCommand(
            email: $this->stringField($body, 'email'),
            password: $this->stringField($body, 'password'),
            name: $this->stringField($body, 'name'),
            auditContext: $this->auditContext->anonymous($request, $this->nullableStringField($body, 'email')),
        );

        /** @var UserView $user */
        $user = $this->handle($this->commandBus, $command);

        return ApiResponse::json($user->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/api/v1/auth/forgot-password', name: 'api_v1_auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $this->handle($this->commandBus, new RequestPasswordResetCommand(
            email: $this->stringField($body, 'email'),
        ));

        // Always 202 regardless of whether the address exists (no enumeration).
        return new JsonResponse(null, Response::HTTP_ACCEPTED);
    }

    #[Route('/api/v1/auth/reset-password', name: 'api_v1_auth_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): Response
    {
        $body = $this->decodeBody($request);

        $this->handle($this->commandBus, new ResetPasswordCommand(
            token: $this->stringField($body, 'token'),
            newPassword: $this->stringField($body, 'new_password'),
            auditContext: $this->auditContext->anonymous($request),
        ));

        return ApiResponse::noContent();
    }

    #[Route('/api/v1/auth/refresh', name: 'api_v1_auth_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $command = new RefreshSessionCommand(
            refreshToken: $this->refreshCookie->read($request),
        );

        /** @var AuthenticationResult $result */
        $result = $this->handle($this->commandBus, $command);

        return $this->withRefreshCookie(
            ApiResponse::json($result->toTokenResponse(), Response::HTTP_OK),
            $result,
        );
    }

    #[Route('/api/v1/auth/logout', name: 'api_v1_auth_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        $command = new LogoutCommand(
            refreshToken: $this->refreshCookie->read($request),
            auditContext: $this->auditContext->fromRequest($request),
        );

        $this->handle($this->commandBus, $command);

        $response = ApiResponse::noContent();
        $response->headers->setCookie($this->refreshCookie->expire());

        return $response;
    }

    private function withRefreshCookie(JsonResponse $response, AuthenticationResult $result): JsonResponse
    {
        $response->headers->setCookie($this->refreshCookie->create($result->refreshToken));

        return $response;
    }
}
