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

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
    ->withSets([
        LevelSetList::UP_TO_PHP_83,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
        SetList::PRIVATIZATION,
    ])
    // Type-declaration rules infer fully-qualified class names; import them as
    // `use` statements so signatures keep using short names. Global classes
    // (e.g. \DateTimeImmutable) stay fully qualified to match the house style.
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withSkipPath(__DIR__ . '/src/*/Infrastructure/*/Migrations');
