<?php
/**
 * Created by PhpStorm.
 * User: pfcode
 * Date: 04.12.16
 * Time: 21:30
 */

namespace Reprostar\MpclWordpress;


class TagHandler
{
    private $machineId;
    private $tagAttributes;

    /**
     * @var Controller
     */
    private $controller;

    public function __construct()
    {
        $this->controller = null;
    }

    public function handle($attr){
        $this->parseQueryVars();
        $this->parseAttributes($attr);
        return $this->controller->execute($this);
    }

    private function parseQueryVars(){
        $this->machineId = get_query_var('machine_id', null);
    }

    /**
     * @param $attr
     */
    private function parseAttributes($attr){
        if(!is_array($attr)){
            $attr = [];
        }

        if(isset($attr['id'])){
            $this->machineId = (int) $attr['id'];
            $this->controller = new BoxController();
        } else if($this->machineId > 0){
            $this->controller = new CatalogSingleController();
        } else{
            $this->controller = new CatalogListController();
        }

        $this->tagAttributes = $attr;
    }

    /**
     * @return int
     */
    public function getMachineId()
    {
        return (int) $this->machineId;
    }

    /**
     * @return array
     */
    public function getTagAttributes()
    {
        return $this->tagAttributes;
    }
}