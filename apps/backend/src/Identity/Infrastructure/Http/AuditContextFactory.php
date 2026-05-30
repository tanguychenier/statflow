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

namespace App\Identity\Infrastructure\Http;

use App\Identity\Domain\ValueObject\AuditContext;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

/**
 * Assembles an {@see AuditContext} from the current HTTP request and security
 * token: the acting user id, the client IP, and the user agent. The actor email
 * is resolved separately by the handler when it loads the user, so the audit row
 * carries the denormalised snapshot the schema expects.
 */
final readonly class AuditContextFactory
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function fromRequest(Request $request, ?string $actorEmail = null): AuditContext
    {
        $user = $this->security->getUser();
        $actorId = $user !== null ? Uuid::fromString($user->getUserIdentifier()) : null;

        return new AuditContext(
            actorId: $actorId,
            actorEmail: $actorEmail,
            ipAddress: $request->getClientIp(),
            userAgent: $request->headers->get('User-Agent'),
        );
    }

    /**
     * Build a context for an unauthenticated action (register, login,
     * forgot/reset password), capturing only request metadata.
     */
    public function anonymous(Request $request, ?string $actorEmail = null): AuditContext
    {
        return new AuditContext(
            actorId: null,
            actorEmail: $actorEmail,
            ipAddress: $request->getClientIp(),
            userAgent: $request->headers->get('User-Agent'),
        );
    }
}
