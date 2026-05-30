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

namespace App\Identity\Infrastructure\Audit;

use App\Identity\Domain\Model\AuditLogEntry;
use App\Identity\Domain\Port\AuditLogger;
use App\Identity\Domain\ValueObject\AuditContext;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Writes one immutable {@see AuditLogEntry} per sensitive action. Persist-only:
 * it never updates or deletes, honouring the append-only contract of the
 * audit_log table (postgres-schema.sql §12).
 */
final readonly class DoctrineAuditLogger implements AuditLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Clock $clock,
    ) {
    }

    public function record(
        string $action,
        AuditContext $context,
        ?Uuid $teamId = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?array $payload = null,
    ): void {
        $entry = new AuditLogEntry(
            teamId: $teamId,
            actorId: $context->actorId,
            actorEmail: $context->actorEmail,
            action: $action,
            resourceType: $resourceType,
            resourceId: $resourceId,
            payload: $payload,
            ipAddress: $context->ipAddress,
            userAgent: $context->userAgent,
            createdAt: $this->clock->now(),
        );

        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }
}
