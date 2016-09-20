<?php

namespace Reprostar\MpclWordpress;

use Reprostar\MpclConnector\MpclConnector;
use Reprostar\MpclConnector\MpclConnectorException;
use Reprostar\MpclConnector\MpclMachineRemoteModel;
use Reprostar\MpclConnector\MpclMachinesSetRemoteModel;

/**
 * Class MpclSynchronisator
 */
class MpclSynchronisator
{
    const USER_AGENT = "WordPress";
    const API_TIMEOUT = 20;

    private $database;

    /**
     * MyPCListConnector constructor.
     * @param MpclDatabase $database
     * @param string $apiKey
     * @param string $apiToken
     */
    public function __construct(MpclDatabase $database, $apiKey, $apiToken)
    {
        $this->database = $database;
        $this->connector = new MpclConnector($apiKey, $apiToken, self::USER_AGENT, self::API_TIMEOUT);
    }

    /**
     * Synchronize all the machines in user's collection at once
     * This method is intended to be ran when called from WordPress plugin options (it does purge all machines for a while)
     * @return int
     */
    public function importMachinesAllAtOnce()
    {
        $partSize = 50;
        $partCounter = 0;
        $machinesImported = 0;
        $firstIteration = 1;

        do {
            // Get machines
            $set = $this->getMachinesPart($partSize, $partCounter * $partSize);
            if(!is_object($set)){
                return false;
            }

            // Purge existing ones on successfull retreive
            if ($firstIteration) {
                $this->database->deleteAllMachines();
            }

            // Import part
            foreach ($set->items as $remoteMachine) {
                $this->database->saveMachine(new MpclMachineModel($remoteMachine));
            }

            $machinesImported += $set->length;
            $firstIteration = 0;
        } while ($set->total > $machinesImported);

        return $machinesImported;
    }

    /**
     * TODO: Synchronize all the machines in user's collection one by one
     * That method is intended to be ran on page visits (since it does not purge all machines for a while)
     * @return int
     */
    public function importMachinesOneByOne(){
        // Currently, there is no implementation of that. Fallback to the first method.
        return $this->importMachinesAllAtOnce();
    }

    /**
     * Wrapper to simply retreive part of machines collection
     * @param $limit
     * @param $offset
     * @return array|bool|MpclMachinesSetRemoteModel
     */
    private function getMachinesPart($limit, $offset)
    {
        try {
            $machinesSet = $this->connector->getMachinesList(false, false, false, 0, 1, $limit, $offset);
        } catch (MpclConnectorException $e) {
            return false;
        }

        return $machinesSet;
    }

    /**
     * Synchronize single machine by ID
     * @param $id
     * @return bool|void
     */
    public function importSingleMachine($id){
        $remoteMachine = $this->getSingleMachine($id);

        if(!$remoteMachine){
            return false;
        }

        return $this->database->saveMachine(new MpclMachineModel($remoteMachine));
    }

    /**
     * Retreive single machine model
     * @param $id
     * @return bool|MpclMachineRemoteModel
     */
    public function getSingleMachine($id){
        try {
            return $this->connector->getMachine($id);
        } catch (MpclConnectorException $e) {
            return false;
        }
    }
}