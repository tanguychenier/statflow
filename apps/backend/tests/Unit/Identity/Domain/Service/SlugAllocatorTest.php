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

namespace App\Tests\Unit\Identity\Domain\Service;

use App\Identity\Domain\Model\Team;
use App\Identity\Domain\Service\SlugAllocator;
use App\Identity\Domain\ValueObject\TeamSlug;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Identity\Support\InMemoryTeamRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SlugAllocator::class)]
final class SlugAllocatorTest extends TestCase
{
    #[Test]
    public function itReturnsTheBaseSlugWhenFree(): void
    {
        $allocator = new SlugAllocator(new InMemoryTeamRepository());

        self::assertSame('acme-corp', $allocator->allocate('ACME Corp')->getValue());
    }

    #[Test]
    public function itAppendsASuffixOnCollision(): void
    {
        $teams = new InMemoryTeamRepository();
        $teams->save(Team::createShared(Uuid::generate(), 'Acme', TeamSlug::fromString('acme'), Uuid::generate(), new DateTimeImmutable()));
        $allocator = new SlugAllocator($teams);

        self::assertSame('acme-2', $allocator->allocate('Acme')->getValue());
    }

    #[Test]
    public function itSkipsMultipleTakenSuffixes(): void
    {
        $teams = new InMemoryTeamRepository();
        foreach (['acme', 'acme-2', 'acme-3'] as $taken) {
            $teams->save(Team::createShared(Uuid::generate(), 'Acme', TeamSlug::fromString($taken), Uuid::generate(), new DateTimeImmutable()));
        }
        $allocator = new SlugAllocator($teams);

        self::assertSame('acme-4', $allocator->allocate('Acme')->getValue());
    }
}
