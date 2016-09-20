<?php

/**
 * Created by PhpStorm.
 * User: pfcode
 * Date: 18.09.16
 * Time: 00:15
 */

namespace Reprostar\MpclWordpress;

use Reprostar\MpclConnector\MpclMachineRemoteModel;

class MpclMachineModel extends MpclMachineRemoteModel
{
    public $synchronized;

    /**
     * MpclMachineModel constructor.
     * @param bool $origin
     */
    public function __construct($origin = false)
    {
        if($origin instanceof MpclMachineRemoteModel){
            /**
             * @var $origin MpclMachineRemoteModel
             */
            $this->populateFromOrigin($origin);
        }
    }

    /**
     * @param MpclMachineRemoteModel $originModel
     */
    private function populateFromOrigin(MpclMachineRemoteModel $originModel){
        $vars = get_object_vars($this);
        $keys = array_keys($vars);

        foreach($keys as $key){
            if(property_exists(get_class($originModel), $key)){
                $this->$key = $originModel->$key;
            }
        }
    }
}