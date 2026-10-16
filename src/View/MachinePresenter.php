<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\View;

use Reprostar\MpclWordpress\Formatting\BbCode;
use Reprostar\MpclWordpress\Machine\Machine;
use Reprostar\MpclWordpress\Machine\Photo;
use Reprostar\MpclWordpress\Support\Cast;

final class MachinePresenter
{
    private const THUMBNAIL_SIZE = 300;
    private const GALLERY_SIZE = 100;

    private BbCode $bbCode;
    private string $placeholderUrl;

    public function __construct(BbCode $bbCode, string $placeholderUrl)
    {
        $this->bbCode = $bbCode;
        $this->placeholderUrl = $placeholderUrl;
    }

    public function forListing(Machine $machine): MachineView
    {
        $view = new MachineView();
        $view->id = $machine->id;
        $view->name = $this->name($machine);
        $view->thumbnailUrl = $this->thumbnailUrl($machine->getFirstPhoto(), self::THUMBNAIL_SIZE);

        return $view;
    }

    public function forSingle(Machine $machine): MachineView
    {
        $view = $this->forListing($machine);
        $view->manufacturer = $this->optional($machine->manufacturer);
        $view->typeName = $this->optional($machine->typeName);
        $view->customName = $this->optional($machine->customName);
        $view->physicalState = $this->physicalState($machine->physicalState);
        $view->descriptionHtml = $this->description((string) $machine->description);

        foreach ($machine->photos as $photo) {
            $view->photos[] = new PhotoView(
                $this->fullUrl($photo),
                $this->thumbnailUrl($photo, self::GALLERY_SIZE)
            );
        }

        return $view;
    }

    public function forBox(Machine $machine, ?string $catalogUrl): MachineView
    {
        $view = $this->forListing($machine);
        $view->manufacturer = $this->optional($machine->manufacturer);
        $view->typeName = $this->optional($machine->typeName);
        $view->physicalState = $this->physicalState($machine->physicalState);
        $view->catalogUrl = $catalogUrl;

        return $view;
    }

    private function name(Machine $machine): string
    {
        return Cast::isBlank($machine->name) ? __('Unnamed', 'mpcl') : (string) $machine->name;
    }

    private function optional(?string $value): ?string
    {
        return Cast::isBlank($value) ? null : $value;
    }

    private function thumbnailUrl(?Photo $photo, int $size): string
    {
        $url = null !== $photo ? $photo->getThumbnail($size) : null;

        return $url ?? $this->placeholderUrl;
    }

    private function fullUrl(Photo $photo): string
    {
        return $photo->origUri ?? $this->placeholderUrl;
    }

    private function physicalState(?int $state): string
    {
        switch ($state) {
            case 1:
                return '1/5 - ' . __('Broken', 'mpcl');
            case 2:
                return '2/5 - ' . __('Needs repair', 'mpcl');
            case 3:
                return '3/5 - ' . __('Partially broken', 'mpcl');
            case 4:
                return '4/5 - ' . __('Good', 'mpcl');
            case 5:
                return '5/5 - ' . __('Very good', 'mpcl');
            default:
                return __('Unknown', 'mpcl');
        }
    }

    private function description(string $source): string
    {
        $html = str_replace(
            ['\r\n', "\n", "\t", '  '],
            ['<br />', '<br />', '&#160; &#160; ', '&#160; '],
            $this->bbCode->toHtml($source)
        );

        return wp_kses_post($html);
    }
}
