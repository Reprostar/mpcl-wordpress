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
    public function execute(array $params)
    {
        $columnsWidth = ( (string) (100 / $params['columnsAmount']) ) . "%";
        $columnsAmount = $params['columnsAmount'];

        $limit = 20;
        $offset = 0;
        if (Configuration::getInstance()->get("cache_autoupdate_enabled") && time() - (int) Configuration::getInstance()->get("last_listing_cache") >= (int) Configuration::getInstance()->get("cache_autoupdate_interval")) {
            // Auto-update machines list
            MpclSynchronisator::getInstance()->importMachinesOneByOne();
        }

        $machines = MpclDatabase::getInstance()->getMachines("id", "DESC", $limit, $offset);
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

        $this->view->assign("columns_width", $columnsWidth);
        $this->view->assign("columns_amount", $columnsAmount);
        $this->view->assign("rows", $rows);

        $this->view->display("listing.tpl");
    }
}