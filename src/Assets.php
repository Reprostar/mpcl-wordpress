<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress;

use Reprostar\MpclWordpress\Shortcode\ShortcodeHandler;

defined('ABSPATH') || exit;

/**
 * Front-end styles and scripts, loaded only on pages that use the shortcode.
 */
final class Assets
{
    private string $baseUrl;
    private string $version;

    public function __construct(string $baseUrl, string $version)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->version = $version;
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueForCurrentPost']);
    }

    public function enqueueForCurrentPost(): void
    {
        $post = get_post();
        if (!$post instanceof \WP_Post || !is_singular()) {
            return;
        }

        foreach (ShortcodeHandler::TAGS as $tag) {
            if (has_shortcode($post->post_content, $tag)) {
                $this->enqueue();

                return;
            }
        }
    }

    /**
     * Also called while rendering the shortcode, for content outside the main
     * post (widgets, archives); WordPress then prints the assets in the footer.
     */
    public function enqueue(): void
    {
        if (is_admin() || wp_script_is('mpcl-front-js')) {
            return;
        }

        wp_enqueue_style('mpcl-front-baguette-css', $this->baseUrl . '/css/baguetteBox.min.css', [], $this->version);
        wp_enqueue_style('mpcl-front-css', $this->baseUrl . '/css/front.css', [], $this->version);
        wp_enqueue_script('mpcl-front-baguette', $this->baseUrl . '/js/baguetteBox.min.js', [], $this->version, true);
        wp_enqueue_script('mpcl-front-js', $this->baseUrl . '/js/front.js', ['mpcl-front-baguette'], $this->version, true);
    }
}
