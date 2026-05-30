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

namespace App\Reporting\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Base class for every business-rule violation raised inside the Reporting
 * context. Each concrete subclass binds to a canonical RFC 9457 problem `type`
 * so the global HTTP exception listener can render it without context knowledge.
 */
abstract class ReportingDomainException extends DomainException
{
}
