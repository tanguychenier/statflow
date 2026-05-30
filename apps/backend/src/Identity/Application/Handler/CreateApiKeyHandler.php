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

use App\Identity\Application\Command\CreateApiKeyCommand;
use App\Identity\Application\DTO\ApiKeyView;
use App\Identity\Application\Service\TeamAccessGuard;
use App\Identity\Domain\Exception\InvalidApiKeyScopeException;
use App\Identity\Domain\Model\ApiKey;
use App\Identity\Domain\Port\ApiKeyRepository;
use App\Identity\Domain\Port\AuditLogger;
use App\Identity\Domain\Service\ApiKeyFactory;
use App\Identity\Domain\ValueObject\ApiKeyScope;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Mints a secret programmatic API key for a team (openapi.yaml createApiKey).
 * Requires admin/owner. Returns the raw key exactly once via ApiKeyView::rawKey;
 * only the SHA-256 hash and the 12-char prefix are persisted (ADR-0009 §4).
 */
final readonly class CreateApiKeyHandler
{
    public function __construct(
        private TeamAccessGuard $accessGuard,
        private ApiKeyRepository $apiKeys,
        private ApiKeyFactory $apiKeyFactory,
        private AuditLogger $auditLogger,
        private Clock $clock,
    ) {
    }

    public function __invoke(CreateApiKeyCommand $command): ApiKeyView
    {
        $actorId = Uuid::fromString($command->actorId);
        $teamId = Uuid::fromString($command->teamId);

        $this->accessGuard->requireTeam($teamId);
        $this->accessGuard->requireRole($actorId, $teamId, TeamRole::Admin, 'api_key:create');

        $scopes = $this->parseScopes($command->scopes);
        $siteIds = array_map(Uuid::fromString(...), $command->siteIds);
        $expiresAt = $this->parseExpiry($command->expiresAt);

        $generated = $this->apiKeyFactory->create($command->live);
        $now = $this->clock->now();

        $apiKey = ApiKey::issue(
            id: Uuid::generate(),
            teamId: $teamId,
            createdBy: $actorId,
            name: $command->name,
            keyHash: $generated->hash,
            keyPrefix: $generated->prefix,
            scopes: $scopes,
            siteIds: $siteIds,
            expiresAt: $expiresAt,
            now: $now,
        );
        $this->apiKeys->save($apiKey);

        $this->auditLogger->record(
            action: 'api_key.created',
            context: $command->auditContext,
            teamId: $teamId,
            resourceType: 'api_key',
            resourceId: $apiKey->id()->getValue(),
            payload: [
                'name' => $command->name,
                'scopes' => $command->scopes,
            ],
        );

        return ApiKeyView::fromEntity($apiKey, $generated->reveal());
    }

    /**
     * @param list<string> $scopes
     *
     * @return non-empty-list<ApiKeyScope>
     */
    private function parseScopes(array $scopes): array
    {
        if ($scopes === []) {
            throw InvalidApiKeyScopeException::empty();
        }

        $parsed = array_map(ApiKeyScope::fromString(...), $scopes);

        /** @var non-empty-list<ApiKeyScope> $parsed */
        return $parsed;
    }

    private function parseExpiry(?string $expiresAt): ?DateTimeImmutable
    {
        if ($expiresAt === null || $expiresAt === '') {
            return null;
        }

        return new DateTimeImmutable($expiresAt);
    }
}
