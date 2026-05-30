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

namespace App\Tests\Unit\Identity\Domain\ValueObject;

use App\Identity\Domain\Exception\InvalidTeamSlugException;
use App\Identity\Domain\ValueObject\TeamSlug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TeamSlug::class)]
final class TeamSlugTest extends TestCase
{
    #[Test]
    public function itAcceptsAValidSlug(): void
    {
        self::assertSame('acme-corp', TeamSlug::fromString('acme-corp')->getValue());
    }

    /**
     * @param non-empty-string $value
     */
    #[Test]
    #[DataProvider('invalidProvider')]
    public function itRejectsInvalidSlugs(string $value): void
    {
        $this->expectException(InvalidTeamSlugException::class);

        TeamSlug::fromString($value);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function invalidProvider(): array
    {
        return [
            ['-leading'],
            ['trailing-'],
            ['Upper'],
            ['has space'],
            ['under_score'],
            [str_repeat('a', 64)],
        ];
    }

    #[Test]
    public function itDerivesASlugFromAFreeFormName(): void
    {
        self::assertSame('alices-team', TeamSlug::fromName("Alice's Team")->getValue());
        self::assertSame('acme-corp', TeamSlug::fromName('  ACME  Corp!! ')->getValue());
    }

    #[Test]
    public function itFallsBackWhenTheNameHasNoUsableCharacters(): void
    {
        self::assertSame('team', TeamSlug::fromName('!!!')->getValue());
    }

    #[Test]
    public function itTransliteratesAccentedCharacters(): void
    {
        self::assertSame('equipe-francaise', TeamSlug::fromName('Équipe Française')->getValue());
    }

    #[Test]
    public function itAppendsANumericSuffixWithinTheLengthLimit(): void
    {
        $slug = TeamSlug::fromString('acme');

        self::assertSame('acme-2', $slug->withSuffix(2)->getValue());
    }

    #[Test]
    public function suffixingALongSlugStaysWithinSixtyThreeCharacters(): void
    {
        $slug = TeamSlug::fromString(str_repeat('a', 63));

        $suffixed = $slug->withSuffix(99);

        self::assertLessThanOrEqual(63, strlen($suffixed->getValue()));
        self::assertStringEndsWith('-99', $suffixed->getValue());
    }
}
