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

use App\Identity\Domain\Model\ApiKey;
use App\Identity\Domain\Service\ApiKeyFactory;
use App\Tests\Identity\Support\SequenceTokenGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiKeyFactory::class)]
final class ApiKeyFactoryTest extends TestCase
{
    #[Test]
    public function itMintsALiveKeyWithThePrefixAndHash(): void
    {
        $factory = new ApiKeyFactory(new SequenceTokenGenerator('rnd'));

        $key = $factory->create();

        self::assertStringStartsWith('sfk_live_', $key->reveal());
        self::assertSame(ApiKey::PREFIX_LENGTH, strlen($key->prefix));
        self::assertSame(substr($key->reveal(), 0, ApiKey::PREFIX_LENGTH), $key->prefix);
        self::assertSame(hash('sha256', $key->reveal()), $key->hash);
    }

    #[Test]
    public function itMintsATestKeyWhenNotLive(): void
    {
        $factory = new ApiKeyFactory(new SequenceTokenGenerator('rnd'));

        $key = $factory->create(false);

        self::assertStringStartsWith('sfk_test_', $key->reveal());
    }

    #[Test]
    public function theHashHelperMatchesTheInstanceHash(): void
    {
        $factory = new ApiKeyFactory(new SequenceTokenGenerator('rnd'));
        $key = $factory->create();

        self::assertSame(ApiKeyFactory::hash($key->reveal()), $key->hash);
    }

    #[Test]
    public function debugInfoMasksTheRawKey(): void
    {
        $key = (new ApiKeyFactory(new SequenceTokenGenerator('rnd')))->create();

        self::assertSame('********', $key->__debugInfo()['rawKey']);
    }
}
