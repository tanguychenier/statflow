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

namespace App\Sites\Infrastructure\Http\Controller;

use App\Sites\Application\Command\RotateTrackerKeyCommand;
use App\Sites\Application\Dto\TrackerKeyRotationResult;
use App\Sites\Infrastructure\Http\ActingUserResolver;
use App\Sites\Infrastructure\Http\BusDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tracker-key rotation endpoint (Admin/Owner only).
 *
 * @see openapi rotateTrackerKey
 */
final readonly class TrackerKeyController
{
    use BusDispatcher;

    public function __construct(
        private MessageBusInterface $commandBus,
        private ActingUserResolver $actingUser,
    ) {
    }

    #[Route('/api/v1/sites/{siteId}/tracker-key/rotate', name: 'api_v1_sites_tracker_key_rotate', methods: ['POST'])]
    public function rotate(string $siteId): JsonResponse
    {
        $command = new RotateTrackerKeyCommand($this->actingUser->userId(), $siteId);

        /** @var TrackerKeyRotationResult $result */
        $result = $this->handle($this->commandBus, $command);

        return new JsonResponse($result->toArray(), Response::HTTP_OK);
    }
}
