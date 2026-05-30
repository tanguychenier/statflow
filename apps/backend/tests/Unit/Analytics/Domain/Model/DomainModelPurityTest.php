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

namespace App\Tests\Unit\Analytics\Domain\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Analytics is ClickHouse-backed; its domain models must stay free of any ORM
 * coupling. Persistence mapping lives in the Doctrine adapters, never on the
 * aggregates themselves.
 */
final class DomainModelPurityTest extends TestCase
{
    private const MODEL_DIRECTORY = __DIR__ . '/../../../../../src/Analytics/Domain/Model';

    private const MODEL_NAMESPACE = 'App\\Analytics\\Domain\\Model\\';

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function modelClasses(): iterable
    {
        $directory = realpath(self::MODEL_DIRECTORY);
        self::assertNotFalse($directory, 'Analytics domain model directory must exist.');

        $files = glob($directory . '/*.php');
        foreach ($files !== false ? $files : [] as $file) {
            $class = self::MODEL_NAMESPACE . basename($file, '.php');
            yield basename($file) => [$class];
        }
    }

    #[Test]
    #[DataProvider('modelClasses')]
    public function modelCarriesNoDoctrineMappingAttribute(string $class): void
    {
        self::assertTrue(class_exists($class) || enum_exists($class) || interface_exists($class), $class . ' is not loadable.');

        $reflection = new ReflectionClass($class);

        $attributes = $reflection->getAttributes();
        foreach ($reflection->getProperties() as $property) {
            $attributes = [...$attributes, ...$property->getAttributes()];
        }
        foreach ($reflection->getMethods() as $method) {
            $attributes = [...$attributes, ...$method->getAttributes()];
        }

        foreach ($attributes as $attribute) {
            self::assertStringStartsNotWith(
                'Doctrine\\',
                $attribute->getName(),
                $class . ' must not carry a Doctrine mapping attribute (' . $attribute->getName() . ').',
            );
        }
    }

    #[Test]
    #[DataProvider('modelClasses')]
    public function modelSourceDoesNotImportDoctrine(string $class): void
    {
        /** @var class-string $class */
        $reflection = new ReflectionClass($class);
        $file = $reflection->getFileName();
        self::assertNotFalse($file, $class . ' must have a source file.');

        $source = (string) file_get_contents($file);

        self::assertStringNotContainsString('use Doctrine\\', $source, $class . ' must not import Doctrine.');
        self::assertStringNotContainsString('#[ORM\\', $source, $class . ' must not use ORM attributes.');
    }
}
