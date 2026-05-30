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

namespace App\Sites\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Base class for every business-rule violation raised inside the Sites context.
 * Each concrete subclass carries a stable RFC 9457 problem `type` so the HTTP
 * layer can render it without knowing the rule that produced it.
 */
abstract class SitesDomainException extends DomainException
{
}
