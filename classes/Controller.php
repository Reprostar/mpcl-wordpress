<?php
/**
 * Created by PhpStorm.
 * User: pfcode
 * Date: 08.10.16
 * Time: 11:08
 */

namespace Reprostar\MpclWordpress;

use \Smarty;

abstract class Controller
{
    /**
     * @var Smarty
     */
    protected $view;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->view = $this->getSmarty();
    }

    /**
     * @return Smarty
     */
    private function getSmarty(){
        $smarty = new Smarty();
        $smarty->force_compile = true;
        $smarty->debugging = false;
        $smarty->caching = true;
        $smarty->cache_lifetime = 120;

        $smarty->setTemplateDir(MpclPlugin::getCwd() . "/templates");

        return $smarty;
    }

    abstract public function execute(array $params);
}