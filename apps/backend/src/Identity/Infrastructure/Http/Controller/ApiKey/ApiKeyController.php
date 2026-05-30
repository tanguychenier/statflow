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

namespace App\Identity\Infrastructure\Http\Controller\ApiKey;

use App\Identity\Application\Command\CreateApiKeyCommand;
use App\Identity\Application\Command\RevokeApiKeyCommand;
use App\Identity\Application\DTO\ApiKeyView;
use App\Identity\Application\Query\ListApiKeysQuery;
use App\Identity\Infrastructure\Http\ActingUserResolver;
use App\Identity\Infrastructure\Http\AuditContextFactory;
use App\Identity\Infrastructure\Http\BusDispatcher;
use App\Identity\Infrastructure\Http\PaginationEnvelope;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Programmatic API-key endpoints (openapi.yaml API Keys). Keys are team-scoped
 * (ADR-0009 §4), so the team is taken from the `team_id` field/param; the raw key
 * value is returned only on creation.
 */
final readonly class ApiKeyController
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

    #[Route('/api/v1/api-keys', name: 'api_v1_api_keys_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $teamId = $request->query->get('team_id');

        if (!is_string($teamId) || $teamId === '') {
            throw new BadRequestHttpException('The "team_id" query parameter is required.');
        }

        /** @var list<ApiKeyView> $keys */
        $keys = $this->handle($this->queryBus, new ListApiKeysQuery($this->actingUser->userId(), $teamId));

        $limit = $request->query->getInt('limit', self::DEFAULT_LIMIT);
        $data = array_map(static fn (ApiKeyView $k): array => $k->toArray(), $keys);

        return ApiResponse::json(PaginationEnvelope::singlePage($data, $limit));
    }

    #[Route('/api/v1/api-keys', name: 'api_v1_api_keys_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $command = new CreateApiKeyCommand(
            actorId: $this->actingUser->userId(),
            teamId: $this->stringField($body, 'team_id'),
            name: $this->stringField($body, 'name'),
            scopes: $this->stringListField($body, 'scopes'),
            siteIds: $this->stringListField($body, 'site_ids'),
            expiresAt: $this->nullableStringField($body, 'expires_at'),
            auditContext: $this->auditContext->fromRequest($request),
        );

        /** @var ApiKeyView $key */
        $key = $this->handle($this->commandBus, $command);

        return ApiResponse::json($key->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/api/v1/api-keys/{key_id}', name: 'api_v1_api_keys_revoke', methods: ['DELETE'])]
    public function revoke(string $key_id, Request $request): Response
    {
        $command = new RevokeApiKeyCommand(
            actorId: $this->actingUser->userId(),
            keyId: $key_id,
            auditContext: $this->auditContext->fromRequest($request),
        );

        $this->handle($this->commandBus, $command);

        return ApiResponse::noContent();
    }
}
