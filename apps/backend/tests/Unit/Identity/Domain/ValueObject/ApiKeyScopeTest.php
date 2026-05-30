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

use App\Identity\Domain\Exception\InvalidApiKeyScopeException;
use App\Identity\Domain\ValueObject\ApiKeyScope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiKeyScope::class)]
final class ApiKeyScopeTest extends TestCase
{
    #[Test]
    public function itExposesTheFixedFiveScopeVocabulary(): void
    {
        self::assertSame(
            ['analytics:read', 'sites:read', 'sites:write', 'reports:read', 'reports:write'],
            ApiKeyScope::values(),
        );
    }

    #[Test]
    public function itParsesAValidScope(): void
    {
        self::assertSame(ApiKeyScope::AnalyticsRead, ApiKeyScope::fromString('analytics:read'));
    }

    #[Test]
    public function itRejectsAnUnknownScope(): void
    {
        $this->expectException(InvalidApiKeyScopeException::class);

        ApiKeyScope::fromString('admin');
    }
}
