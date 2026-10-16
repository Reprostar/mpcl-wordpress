<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\View;

final class PhotoView
{
    public string $fullUrl;
    public string $smallUrl;

    public function __construct(string $fullUrl, string $smallUrl)
    {
        $this->fullUrl = $fullUrl;
        $this->smallUrl = $smallUrl;
    }
}
