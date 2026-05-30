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

namespace App\Shared\Domain\Repository;

/**
 * Marker interface for driven repository ports.
 *
 * Each aggregate defines its own repository interface (e.g. `SiteRepository`)
 * extending this marker in its own context's Domain layer; the persistence
 * adapter lives in Infrastructure and MAY extend {@see \App\Shared\Infrastructure\Persistence\DoctrineRepository}.
 */
interface Repository
{
}
