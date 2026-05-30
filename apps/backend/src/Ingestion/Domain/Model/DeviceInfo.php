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

namespace App\Ingestion\Domain\Model;

/**
 * Device / browser / OS fields parsed from the User-Agent at enrichment time.
 * `deviceType` is one of 'desktop' | 'mobile' | 'tablet' (clickhouse-schema.sql).
 */
final readonly class DeviceInfo
{
    public function __construct(
        public string $deviceType,
        public string $browser,
        public string $browserVersion,
        public string $os,
        public string $osVersion,
    ) {
    }

    public static function unknown(): self
    {
        return new self('desktop', '', '', '', '');
    }
}
