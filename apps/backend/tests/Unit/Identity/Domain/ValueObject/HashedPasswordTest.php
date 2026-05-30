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

use App\Identity\Domain\Exception\InvalidPasswordHashException;
use App\Identity\Domain\ValueObject\HashedPassword;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HashedPassword::class)]
final class HashedPasswordTest extends TestCase
{
    #[Test]
    public function itWrapsAnExistingHash(): void
    {
        $hash = HashedPassword::fromHash('$2y$10$abcdef');

        self::assertSame('$2y$10$abcdef', $hash->getValue());
        self::assertSame('$2y$10$abcdef', (string) $hash);
    }

    #[Test]
    public function itRejectsAnEmptyHash(): void
    {
        $this->expectException(InvalidPasswordHashException::class);

        HashedPassword::fromHash('');
    }
}
