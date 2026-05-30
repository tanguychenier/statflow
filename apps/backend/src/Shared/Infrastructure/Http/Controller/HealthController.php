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

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liveness probe — confirms the PHP process is alive.
 * Returns 200 immediately with no external dependency checks.
 *
 * GET /healthz
 */
#[Route('/healthz', name: 'health_liveness', methods: ['GET'])]
final class HealthController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
        ], Response::HTTP_OK);
    }
}
