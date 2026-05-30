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

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Resolves the authenticated user's UUID from the Symfony security token for the
 * Identity HTTP layer. The security user identifier is the user's UUID
 * (ADR-0009), so commands receive a plain id string without coupling controllers
 * to a concrete user class.
 */
final readonly class ActingUserResolver
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
