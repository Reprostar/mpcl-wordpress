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
    private $view;

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

        $smarty->setTemplateDir(Plugin::getCwd() . "/templates");

        return $smarty;
    }

    protected function assign($key, $value){
        $this->view->assign($key, $value);
    }

    protected function display($template){
        return $this->view->fetch($template);
    }

    abstract public function execute(TagHandler $tagHandler);
}