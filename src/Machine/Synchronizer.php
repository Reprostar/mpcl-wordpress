<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Machine;

use Reprostar\MpclWordpress\Api\ApiClient;
use Reprostar\MpclWordpress\Api\ApiClientFactory;
use Reprostar\MpclWordpress\Api\ApiException;

defined('ABSPATH') || exit;

/**
 * Replaces the local copy of the collection with the one on mypclist.net.
 */
final class Synchronizer
{
    private const PAGE_SIZE = 50;

    private ApiClientFactory $clients;
    private MachineRepository $machines;

    public function __construct(ApiClientFactory $clients, MachineRepository $machines)
    {
        $this->clients = $clients;
        $this->machines = $machines;
    }

    /**
     * Fetches the whole collection first and only then replaces the local copy
     * in one transaction, so a failing API keeps the previous copy and visitors
     * never see a half-imported catalogue.
     *
     * @return int number of machines stored
     * @throws ApiException
     */
    public function import(string $apiKey, string $apiToken): int
    {
        $machines = $this->fetchAll($this->clients->create($apiKey, $apiToken));

        return $this->machines->replaceAll($machines, time());
    }

    /**
     * @return list<Machine>
     * @throws ApiException
     */
    private function fetchAll(ApiClient $client): array
    {
        $machines = [];
        $page = 0;
        $maxPages = 1;

        do {
            $set = $client->getMachinesPage(self::PAGE_SIZE, $page * self::PAGE_SIZE);

            if (0 === $page) {
                if ([] === $set->items && 0 < $set->total) {
                    throw new ApiException('the API reported machines but returned none');
                }
                $maxPages = (int) ceil($set->total / self::PAGE_SIZE) + 1;
            }

            if ([] === $set->items) {
                break;
            }

            foreach ($set->items as $machine) {
                $machines[] = $machine;
            }
            ++$page;
        } while ($set->total > count($machines) && $page < $maxPages);

        return $machines;
    }
}
