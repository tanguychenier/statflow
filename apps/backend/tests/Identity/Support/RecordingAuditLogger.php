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

namespace App\Tests\Identity\Support;

use App\Identity\Domain\Port\AuditLogger;
use App\Identity\Domain\ValueObject\AuditContext;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Captures audit entries so tests can assert that sensitive actions are recorded
 * with the expected action name and context.
 */
final class RecordingAuditLogger implements AuditLogger
{
    /**
     * @var list<array{action: string, context: AuditContext, teamId: ?Uuid, resourceType: ?string, resourceId: ?string, payload: ?array<string, mixed>}>
     */
    public array $entries = [];

    public function record(
        string $action,
        AuditContext $context,
        ?Uuid $teamId = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?array $payload = null,
    ): void {
        $this->entries[] = [
            'action' => $action,
            'context' => $context,
            'teamId' => $teamId,
            'resourceType' => $resourceType,
            'resourceId' => $resourceId,
            'payload' => $payload,
        ];
    }

    /**
     * @return list<string>
     */
    public function actions(): array
    {
        return array_map(static fn (array $e): string => $e['action'], $this->entries);
    }

    public function hasAction(string $action): bool
    {
        return in_array($action, $this->actions(), true);
    }
}
