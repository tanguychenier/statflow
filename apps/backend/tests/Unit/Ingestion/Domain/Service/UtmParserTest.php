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

namespace App\Tests\Unit\Ingestion\Domain\Service;

use App\Ingestion\Domain\Service\CanonicalEventFactory;
use App\Ingestion\Domain\Service\UtmParser;
use App\Tests\Ingestion\Support\EventFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UtmParser::class)]
final class UtmParserTest extends TestCase
{
    private UtmParser $parser;

    private CanonicalEventFactory $factory;

    protected function setUp(): void
    {
        $this->parser = new UtmParser();
        $this->factory = new CanonicalEventFactory();
    }

    #[Test]
    public function itParsesUtmParametersFromTheUrlQueryString(): void
    {
        $event = $this->factory->fromCanonical(EventFixtures::canonicalPageview([
            'url' => 'https://example.com/p?utm_source=newsletter&utm_medium=email&utm_campaign=launch&utm_term=t&utm_content=c',
        ]));

        $enriched = $this->parser->enrich($event);

        self::assertSame('newsletter', $enriched->utmSource);
        self::assertSame('email', $enriched->utmMedium);
        self::assertSame('launch', $enriched->utmCampaign);
        self::assertSame('t', $enriched->utmTerm);
        self::assertSame('c', $enriched->utmContent);
    }

    #[Test]
    public function itLeavesUtmNullWhenTheUrlHasNoQuery(): void
    {
        $event = $this->factory->fromCanonical(EventFixtures::canonicalPageview([
            'url' => 'https://example.com/clean',
        ]));

        $enriched = $this->parser->enrich($event);

        self::assertNull($enriched->utmSource);
        self::assertNull($enriched->utmMedium);
    }

    #[Test]
    public function explicitlySuppliedUtmTakesPrecedenceOverTheUrl(): void
    {
        $event = $this->factory->fromCanonical(EventFixtures::canonicalPageview([
            'url' => 'https://example.com/p?utm_source=fromurl',
            'utm_source' => 'explicit',
        ]));

        $enriched = $this->parser->enrich($event);

        self::assertSame('explicit', $enriched->utmSource);
    }
}
