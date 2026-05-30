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

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Base class for every business-rule violation raised inside the Identity
 * context. Each concrete subclass binds itself to a canonical
 * {@see \App\Shared\Domain\Exception\ErrorType} so the HTTP layer can render the
 * documented RFC 9457 Problem Details without knowing the rule that produced it.
 */
abstract class IdentityException extends DomainException
{
}
