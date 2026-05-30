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

namespace App\Identity\Domain\Port;

use App\Identity\Domain\ValueObject\AuditContext;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Append-only audit trail for sensitive mutations (postgres-schema.sql §12).
 * Implementations write one immutable row; they never update or delete.
 */
interface AuditLogger
{
    /**
     * @param array<string, mixed>|null $payload Relevant metadata or before/after snapshot
     */
    public function record(
        string $action,
        AuditContext $context,
        ?Uuid $teamId = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?array $payload = null,
    ): void;
}
