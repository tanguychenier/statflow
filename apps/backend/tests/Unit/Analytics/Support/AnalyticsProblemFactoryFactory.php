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

namespace App\Tests\Unit\Analytics\Support;

use App\Analytics\Infrastructure\Http\Support\AnalyticsProblemFactory;
use App\Shared\Infrastructure\Http\ProblemDetailsFactory;

/**
 * Builds an {@see AnalyticsProblemFactory} wired to a deterministic trace-id, so
 * controller tests get a real problem factory without repeating the wiring.
 */
final class AnalyticsProblemFactoryFactory
{
    public static function create(): AnalyticsProblemFactory
    {
        return new AnalyticsProblemFactory(
            new ProblemDetailsFactory(new FixedTraceIdProvider()),
        );
    }
}
