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

use App\Sites\Application\Command\ReplaceSiteSettingsCommand;
use App\Sites\Application\Dto\SiteSettingsView;
use App\Sites\Application\Query\GetSiteSettingsQuery;
use App\Sites\Infrastructure\Http\ActingUserResolver;
use App\Sites\Infrastructure\Http\BusDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Site configuration endpoints: get (GET) and full replace (PUT).
 *
 * @see openapi getSiteSettings, updateSiteSettings
 */
final readonly class SiteSettingsController
{
    use BusDispatcher;

    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private ActingUserResolver $actingUser,
    ) {
    }

    #[Route('/api/v1/sites/{siteId}/settings', name: 'api_v1_sites_settings_get', methods: ['GET'])]
    public function get(string $siteId): JsonResponse
    {
        $query = new GetSiteSettingsQuery($this->actingUser->userId(), $siteId);

        /** @var SiteSettingsView $result */
        $result = $this->handle($this->queryBus, $query);

        return new JsonResponse($result->toArray(), Response::HTTP_OK);
    }

    #[Route('/api/v1/sites/{siteId}/settings', name: 'api_v1_sites_settings_update', methods: ['PUT'])]
    public function update(string $siteId, Request $request): JsonResponse
    {
        $command = new ReplaceSiteSettingsCommand(
            actingUserId: $this->actingUser->userId(),
            siteId: $siteId,
            settings: $this->decodeBody($request),
        );

        /** @var SiteSettingsView $result */
        $result = $this->handle($this->commandBus, $command);

        return new JsonResponse($result->toArray(), Response::HTTP_OK);
    }
}
