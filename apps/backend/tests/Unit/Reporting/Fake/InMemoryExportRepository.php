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

namespace App\Tests\Unit\Reporting\Fake;

use App\Reporting\Domain\Model\Export;
use App\Reporting\Domain\Port\ExportRepository;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * In-memory {@see ExportRepository} for application-layer tests.
 */
final class InMemoryExportRepository implements ExportRepository
{
    public int $saveCount = 0;

    /**
     * @var array<string, Export>
     */
    private array $exports = [];

    public function save(Export $export): void
    {
        ++$this->saveCount;
        $this->exports[$export->id()->getValue()] = $export;
    }

    public function findById(Uuid $siteId, Uuid $id): ?Export
    {
        $export = $this->exports[$id->getValue()] ?? null;

        if ($export === null || !$export->siteId()->equals($siteId)) {
            return null;
        }

        return $export;
    }

    public function findByIdUnscoped(Uuid $id): ?Export
    {
        return $this->exports[$id->getValue()] ?? null;
    }
}
