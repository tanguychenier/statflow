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

use App\Identity\Application\Command\RevokeApiKeyCommand;
use App\Identity\Application\Service\TeamAccessGuard;
use App\Identity\Domain\Exception\ApiKeyNotFoundException;
use App\Identity\Domain\Port\ApiKeyRepository;
use App\Identity\Domain\Port\AuditLogger;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Revokes an API key (openapi.yaml revokeApiKey). Requires admin/owner on the
 * key's team. Revocation is idempotent and irreversible — the key stops
 * authenticating immediately.
 */
final readonly class RevokeApiKeyHandler
{
    public function __construct(
        private TeamAccessGuard $accessGuard,
        private ApiKeyRepository $apiKeys,
        private AuditLogger $auditLogger,
        private Clock $clock,
    ) {
    }

    public function __invoke(RevokeApiKeyCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);
        $keyId = Uuid::fromString($command->keyId);

        $apiKey = $this->apiKeys->findById($keyId);

        if ($apiKey === null) {
            throw ApiKeyNotFoundException::withId($keyId);
        }

        // Authorise against the key's own team; a non-member sees a 404, not the
        // key's existence in another team.
        $this->accessGuard->requireTeam($apiKey->teamId());
        $this->accessGuard->requireRole($actorId, $apiKey->teamId(), TeamRole::Admin, 'api_key:revoke');

        $apiKey->revoke($this->clock->now());
        $this->apiKeys->save($apiKey);

        $this->auditLogger->record(
            action: 'api_key.revoked',
            context: $command->auditContext,
            teamId: $apiKey->teamId(),
            resourceType: 'api_key',
            resourceId: $keyId->getValue(),
        );
    }
}
