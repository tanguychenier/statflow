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

namespace App\Reporting\Infrastructure\Http;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Resolves the acting user id from Symfony's security token. The Identity
 * context exposes the user's UUID as the security user identifier (ADR-0009);
 * this adapter reads it without coupling Reporting to a concrete User class.
 */
final readonly class SecurityActingUserResolver implements ActingUserResolver
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function userId(): string
    {
        $user = $this->security->getUser();

        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Authentication required.');
        }

        return $user->getUserIdentifier();
    }
}
