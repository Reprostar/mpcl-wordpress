<?php
/**
 * Created by PhpStorm.
 * User: pfcode
 * Date: 08.10.16
 * Time: 11:20
 */

namespace Reprostar\MpclWordpress;


use JBBCode\DefaultCodeDefinitionSet;
use JBBCode\Parser;

class CatalogSingleController extends Controller
{
    public function execute(TagHandler $tagHandler)
    {
        $machineId = $tagHandler->getMachineId();
        $machine = MpclDatabase::getInstance()->getMachine($machineId);
        if(!is_object($machine)){
            $machine = false;
        } else{
            $machine = $machine->toAssoc();
            $machine['thumbnail_uri'] = Utils::getPhotoURI(count($machine['photos']) ? $machine['photos'][0] : '', 300);
            $machine['name'] = !empty($machine['name']) ? $machine['name'] : __('Unnamed', 'mpcl');

            foreach($machine['photos'] as $k => $photo){
                $machine['photos'][$k] = array(
                    "raw" => Utils::getPhotoURI($photo),
                    300 => Utils::getPhotoURI($photo, 300)
                );
            }

            $parser = new Parser();
            $parser->addCodeDefinitionSet(new DefaultCodeDefinitionSet());
            $parser->parse($machine['description']);

            $html = $parser->getAsHtml();
            $pattern = array("\\r\\n", "\n", "\t", '  ', '  ');
            $replace = array('<br />', '<br />', '&#160; &#160; ', '&#160; ', ' &#160;');
            $html = str_replace($pattern, $replace, $html);

            $machine['description'] = $html;
            $machine['physical_state'] = Utils::getStateString($machine['physical_state']);
        }

        $this->view->assign("machine", $machine);

        $this->view->display("single.tpl");
    }
}