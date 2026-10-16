<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\View;

/**
 * A machine prepared for a template. Optional text fields are null when the
 * template should leave their row out.
 */
final class MachineView
{
    public int $id = 0;
    public string $name = '';
    public ?string $manufacturer = null;
    public ?string $typeName = null;
    public ?string $customName = null;
    public string $physicalState = '';
    public string $thumbnailUrl = '';
    public string $descriptionHtml = '';

    /** @var list<PhotoView> */
    public array $photos = [];

    /** Link to the machine on the catalogue page, null when no catalogue page is configured. */
    public ?string $catalogUrl = null;
}
