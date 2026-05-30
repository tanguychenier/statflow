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

namespace App\Shared\Infrastructure\Http\Controller;

use Doctrine\DBAL\Connection;
use Predis\ClientInterface as RedisClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Readiness probe — confirms all critical external dependencies are reachable.
 * Returns 200 when ready, 503 when one or more dependencies are down.
 *
 * GET /readyz
 */
#[Route('/readyz', name: 'health_readiness', methods: ['GET'])]
final readonly class ReadinessController
{
    public function __construct(
        private Connection $connection,
        private RedisClient $redis,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $checks = [
            'postgres' => $this->checkPostgres(),
            'redis' => $this->checkRedis(),
        ];

        $allHealthy = !in_array(false, $checks, true);
        $status = $allHealthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return new JsonResponse(
            [
                'status' => $allHealthy ? 'ok' : 'degraded',
                'checks' => $checks,
            ],
            $status,
        );
    }

    private function checkPostgres(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            $response = $this->redis->ping();

            return $response !== null;
        } catch (Throwable) {
            return false;
        }
    }
}
