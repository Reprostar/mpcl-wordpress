<?php
/**
 * Created by PhpStorm.
 * User: pfcode
 * Date: 08.10.16
 * Time: 11:20
 */

namespace Reprostar\MpclWordpress;

class BoxController extends Controller
{
    public function execute(TagHandler $tagHandler)
    {
        $machineId = $tagHandler->getMachineId();
        $machine = Database::getInstance()->getMachine($machineId);
        if(!is_object($machine)){
            $machine = false;
        } else{
            $machine = $machine->toAssoc();
            $machine['thumbnail_uri'] = Utils::getPhotoURI(count($machine['photos']) ? $machine['photos'][0] : '', 300);
            $machine['name'] = !empty($machine['name']) ? $machine['name'] : __('Unnamed', 'mpcl');
            $machine['physical_state'] = Utils::getStateString($machine['physical_state']);


            $machine['url'] = $this->getCatalogLink($machineId);
        }

        $this->assign("machine", $machine);
        $this->assign("embedSettings", [
            'full_width' => Configuration::getInstance()->get("box_full_width", false)
        ]);

        return $this->display("box.tpl");
    }

    /**
     * @param $machineId
     * @return false|string
     */
    private function getCatalogLink($machineId){
        $catalogPageId = Configuration::getInstance()->get("catalog_page_id", false);
        $catalogPageLink = get_permalink($catalogPageId);

        if(!$catalogPageId || empty($catalogPageLink)){
            return false;
        }

        $catalogPageLink = add_query_arg([
            'machine_id' => $machineId
        ], $catalogPageLink);

        return $catalogPageLink;
    }
}