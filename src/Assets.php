<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress;

final class Assets
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function register(): void
    {
        add_action('init', [$this, 'enqueue']);
    }

    public function enqueue(): void
    {
        wp_register_style('mpcl-front-css', $this->baseUrl . '/css/front.css');
        wp_register_style('mpcl-front-baguette-css', $this->baseUrl . '/css/baguetteBox.min.css');
        wp_enqueue_style('mpcl-front-baguette-css');
        wp_enqueue_style('mpcl-front-css');

        wp_register_script('mpcl-front-baguette', $this->baseUrl . '/js/baguetteBox.min.js');
        wp_register_script('mpcl-front-js', $this->baseUrl . '/js/front.js');
        wp_enqueue_script('mpcl-front-baguette');
        wp_enqueue_script('mpcl-front-js');
    }
}
