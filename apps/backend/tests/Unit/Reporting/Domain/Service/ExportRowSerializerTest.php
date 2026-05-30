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

namespace App\Tests\Unit\Reporting\Domain\Service;

use App\Reporting\Domain\Model\ExportFormat;
use App\Reporting\Domain\Service\ExportRowSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExportRowSerializer::class)]
final class ExportRowSerializerTest extends TestCase
{
    private ExportRowSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new ExportRowSerializer();
    }

    #[Test]
    public function csvEmitsHeaderAndRows(): void
    {
        $csv = $this->serializer->serialize(ExportFormat::Csv, [
            [
                'page' => '/home',
                'views' => 10,
            ],
            [
                'page' => '/about',
                'views' => 4,
            ],
        ]);

        self::assertSame("page,views\r\n/home,10\r\n/about,4\r\n", $csv);
    }

    #[Test]
    public function csvQuotesSpecialCharacters(): void
    {
        $csv = $this->serializer->serialize(ExportFormat::Csv, [
            [
                'label' => 'a,b',
                'note' => "line1\nline2",
                'quote' => 'say "hi"',
            ],
        ]);

        self::assertSame("label,note,quote\r\n\"a,b\",\"line1\nline2\",\"say \"\"hi\"\"\"\r\n", $csv);
    }

    #[Test]
    public function csvHandlesNullAndBool(): void
    {
        $csv = $this->serializer->serialize(ExportFormat::Csv, [
            [
                'a' => null,
                'b' => true,
                'c' => false,
            ],
        ]);

        self::assertSame("a,b,c\r\n,true,false\r\n", $csv);
    }

    #[Test]
    public function csvUnionsColumnsAcrossRows(): void
    {
        $csv = $this->serializer->serialize(ExportFormat::Csv, [
            [
                'a' => 1,
            ],
            [
                'b' => 2,
            ],
        ]);

        self::assertSame("a,b\r\n1,\r\n,2\r\n", $csv);
    }

    #[Test]
    public function emptyRowsProduceEmptyString(): void
    {
        self::assertSame('', $this->serializer->serialize(ExportFormat::Csv, []));
        self::assertSame('', $this->serializer->serialize(ExportFormat::Ndjson, []));
    }

    #[Test]
    public function ndjsonEmitsOneObjectPerLine(): void
    {
        $ndjson = $this->serializer->serialize(ExportFormat::Ndjson, [
            [
                'page' => '/home',
                'views' => 10,
            ],
            [
                'page' => '/about',
                'views' => 4,
            ],
        ]);

        self::assertSame("{\"page\":\"/home\",\"views\":10}\n{\"page\":\"/about\",\"views\":4}\n", $ndjson);
    }
}
