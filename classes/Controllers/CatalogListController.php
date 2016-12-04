<?php
/**
 * Created by PhpStorm.
 * User: pfcode
 * Date: 08.10.16
 * Time: 11:20
 */

namespace Reprostar\MpclWordpress;


class CatalogListController extends Controller
{
    public function execute(TagHandler $tagHandler)
    {
        $params = $tagHandler->getTagAttributes();

        $columnsWidth = ( (string) (100 / $params['columns']) ) . "%";
        $columnsAmount = $params['columns'];

        $limit = 20;
        $offset = 0;
        if (Configuration::getInstance()->get("cache_autoupdate_enabled") && time() - (int) Configuration::getInstance()->get("last_listing_cache") >= (int) Configuration::getInstance()->get("cache_autoupdate_interval")) {
            // Auto-update machines list
            Synchronizator::getInstance()->importMachinesOneByOne();
        }

        $machines = Database::getInstance()->getMachines("id", "DESC", $limit, $offset);
        if(!is_array($machines)){
            $machines = array();
        }

        $rows = array();
        for($i = 0; $i < count($machines); $i++){
            $machine = $machines[$i]->toAssoc();
            $row_idx = (int) floor($i / $columnsAmount);

            if(!isset($rows[$row_idx]) || !is_array($rows[$row_idx])){
                $rows[$row_idx] = array();
            }

            $machine['thumbnail_uri'] = Utils::getPhotoURI(count($machine['photos']) ? $machine['photos'][0] : '', 300);
            $machine['name'] = !empty($machine['name']) ? $machine['name'] : __('Unnamed', 'mpcl');

            $rows[$row_idx][] = $machine;
        }

        $this->assign("columns_width", $columnsWidth);
        $this->assign("columns_amount", $columnsAmount);
        $this->assign("rows", $rows);

        return $this->display("listing.tpl");
    }
}