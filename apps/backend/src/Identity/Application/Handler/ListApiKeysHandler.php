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

namespace App\Identity\Application\Handler;

use App\Identity\Application\DTO\ApiKeyView;
use App\Identity\Application\Query\ListApiKeysQuery;
use App\Identity\Application\Service\TeamAccessGuard;
use App\Identity\Domain\Model\ApiKey;
use App\Identity\Domain\Port\ApiKeyRepository;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Lists a team's active (non-revoked) API keys (openapi.yaml listApiKeys). Raw key
 * values are never returned after creation — only the masked prefix. Requires
 * admin/owner, since keys are an administrative resource.
 */
final readonly class ListApiKeysHandler
{
    public function __construct(
        private TeamAccessGuard $accessGuard,
        private ApiKeyRepository $apiKeys,
    ) {
    }

    /**
     * @return list<ApiKeyView>
     */
    public function __invoke(ListApiKeysQuery $query): array
    {
        $actorId = Uuid::fromString($query->actorId);
        $teamId = Uuid::fromString($query->teamId);

        $this->accessGuard->requireTeam($teamId);
        $this->accessGuard->requireRole($actorId, $teamId, TeamRole::Admin, 'api_key:list');

        return array_map(
            static fn (ApiKey $key): ApiKeyView => ApiKeyView::fromEntity($key),
            $this->apiKeys->findActiveByTeam($teamId),
        );
    }
}
