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

namespace App\Sites\Infrastructure\Http;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Resolves the id of the authenticated dashboard user for the current request.
 *
 * Lives in Infrastructure because it bridges the HTTP/security layer to the
 * application commands, which only take a plain user-id string. Implementations
 * read the active security token; tests provide a fixed id.
 */
interface ActingUserResolver
{
    /**
     * The authenticated user's UUID, as a string.
     *
     * @throws UnauthorizedHttpException when no user is authenticated
     */
    public function userId(): string;
}
