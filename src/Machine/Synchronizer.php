<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Machine;

use Reprostar\MpclWordpress\Api\ApiClient;

defined('ABSPATH') || exit;

/**
 * Replaces the local copy of the collection with the one on mypclist.net.
 */
final class Synchronizer
{
    private const PAGE_SIZE = 50;

    private ApiClient $client;
    private MachineRepository $machines;

    public function __construct(ApiClient $client, MachineRepository $machines)
    {
        $this->client = $client;
        $this->machines = $machines;
    }

    /**
     * Fetches the whole collection first and only then replaces the local copy
     * in one transaction, so a failing API keeps the previous copy and visitors
     * never see a half-imported catalogue.
     *
     * @return int|null number of machines stored, null when the API failed
     */
    public function importAll(): ?int
    {
        $machines = $this->fetchAll();
        if (null === $machines) {
            return null;
        }

        return $this->machines->replaceAll($machines, time());
    }

    /**
     * @return list<Machine>|null
     */
    private function fetchAll(): ?array
    {
        $machines = [];
        $page = 0;
        $maxPages = 1;

        do {
            try {
                $set = $this->client->getMachinesPage(self::PAGE_SIZE, $page * self::PAGE_SIZE);
            } catch (\Throwable $e) {
                error_log('mpcl: machine import failed: ' . $e->getMessage());

                return null;
            }

            if (0 === $page) {
                if ([] === $set->items && 0 < $set->total) {
                    error_log('mpcl: machine import failed: the API reported machines but returned none');

                    return null;
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
