<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Machine;

use Reprostar\MpclWordpress\Api\ApiClient;

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
     * The local table is purged only after the first page has been fetched, so a
     * failing API leaves the previous copy in place.
     *
     * @return int|null number of machines received, null when the API failed
     */
    public function importAll(): ?int
    {
        $imported = 0;
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

                $this->machines->deleteAll();
                $maxPages = (int) ceil($set->total / self::PAGE_SIZE) + 1;
            }

            if ([] === $set->items) {
                break;
            }

            $now = time();
            foreach ($set->items as $machine) {
                $this->machines->save($machine, $now);
            }

            $imported += count($set->items);
            ++$page;
        } while ($set->total > $imported && $page < $maxPages);

        return $imported;
    }
}
