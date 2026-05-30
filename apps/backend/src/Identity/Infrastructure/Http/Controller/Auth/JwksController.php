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

namespace App\Identity\Infrastructure\Http\Controller\Auth;

use App\Identity\Infrastructure\Token\JwksProvider;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Publishes the ES256 public key set used to verify access tokens
 * (openapi.yaml getJwks). Unauthenticated by design.
 */
final readonly class JwksController
{
    public function __construct(
        private JwksProvider $jwks,
    ) {
    }

    #[Route('/api/v1/auth/.well-known/jwks.json', name: 'api_v1_auth_jwks', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return ApiResponse::json($this->jwks->jwks());
    }
}
