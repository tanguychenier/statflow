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

namespace App\Tests\Unit\Analytics\Application\Query;

use App\Analytics\Application\Query\QueryFingerprint;
use App\Analytics\Application\Query\SqlClause;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryFingerprint::class)]
#[CoversClass(SqlClause::class)]
final class QueryFingerprintTest extends TestCase
{
    #[Test]
    public function identicalClausesProduceTheSameKey(): void
    {
        $a = [
            new SqlClause('SELECT 1', [
                'p0' => 'x',
            ])];
        $b = [
            new SqlClause('SELECT 1', [
                'p0' => 'x',
            ])];

        self::assertSame(
            QueryFingerprint::fromClauses('aggregate', $a),
            QueryFingerprint::fromClauses('aggregate', $b),
        );
    }

    #[Test]
    public function differentBindingsProduceDifferentKeys(): void
    {
        $a = [
            new SqlClause('SELECT 1', [
                'p0' => 'x',
            ])];
        $b = [
            new SqlClause('SELECT 1', [
                'p0' => 'y',
            ])];

        self::assertNotSame(
            QueryFingerprint::fromClauses('aggregate', $a),
            QueryFingerprint::fromClauses('aggregate', $b),
        );
    }

    #[Test]
    public function theKindPrefixesTheKey(): void
    {
        $clauses = [new SqlClause('SELECT 1', [])];

        self::assertStringStartsWith('breakdown:', QueryFingerprint::fromClauses('breakdown', $clauses));
    }

    #[Test]
    public function differentKindsDoNotCollide(): void
    {
        $clauses = [new SqlClause('SELECT 1', [])];

        self::assertNotSame(
            QueryFingerprint::fromClauses('aggregate', $clauses),
            QueryFingerprint::fromClauses('timeseries', $clauses),
        );
    }
}
