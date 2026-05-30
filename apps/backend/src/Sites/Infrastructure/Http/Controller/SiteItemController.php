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

use App\Sites\Application\Command\DeleteSiteCommand;
use App\Sites\Application\Command\UpdateSiteCommand;
use App\Sites\Application\Dto\SiteView;
use App\Sites\Application\Query\GetSiteQuery;
use App\Sites\Infrastructure\Http\ActingUserResolver;
use App\Sites\Infrastructure\Http\BusDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Item endpoints for a single site: get (GET), update (PATCH), delete (DELETE).
 *
 * @see openapi getSite, updateSite, deleteSite
 */
final readonly class SiteItemController
{
    use BusDispatcher;

    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus,
        private ActingUserResolver $actingUser,
    ) {
    }

    #[Route('/api/v1/sites/{siteId}', name: 'api_v1_sites_get', methods: ['GET'])]
    public function get(string $siteId): JsonResponse
    {
        $query = new GetSiteQuery($this->actingUser->userId(), $siteId);

        /** @var SiteView $result */
        $result = $this->handle($this->queryBus, $query);

        return new JsonResponse($result->toArray(), Response::HTTP_OK);
    }

    #[Route('/api/v1/sites/{siteId}', name: 'api_v1_sites_update', methods: ['PATCH'])]
    public function update(string $siteId, Request $request): JsonResponse
    {
        $body = $this->decodeBody($request);

        $command = new UpdateSiteCommand(
            actingUserId: $this->actingUser->userId(),
            siteId: $siteId,
            name: $this->nullableStringField($body, 'name'),
            domain: $this->nullableStringField($body, 'domain'),
            timezone: $this->nullableStringField($body, 'timezone'),
        );

        /** @var SiteView $result */
        $result = $this->handle($this->commandBus, $command);

        return new JsonResponse($result->toArray(), Response::HTTP_OK);
    }

    #[Route('/api/v1/sites/{siteId}', name: 'api_v1_sites_delete', methods: ['DELETE'])]
    public function delete(string $siteId): JsonResponse
    {
        $command = new DeleteSiteCommand($this->actingUser->userId(), $siteId);

        $this->handle($this->commandBus, $command);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
