<?php

/**
 * Frontend single view: theme template override + scoped assets for a CPT.
 *
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\CustomPostTypes;

/**
 * Class FrontendSingleCptTemplate
 *
 * @package AmeliaBooking\Infrastructure\WP\CustomPostTypes
 */
class FrontendSingleCptTemplate
{
    /** @var string */
    private $postType;

    /** @var string */
    private $styleHandle;

    /** @var string */
    private $styleUrl;

    /** @var string */
    private $scriptHandle;

    /** @var string */
    private $scriptUrl;

    /** @var string */
    private $singleTemplateAbsolutePath;

    /**
     * @param string $postType                     Registered CPT slug
     * @param string $styleHandle                  wp_enqueue_style handle
     * @param string $styleUrl                     Full URL to CSS
     * @param string $scriptHandle                 wp_enqueue_script handle
     * @param string $scriptUrl                    Full URL to JS
     * @param string $singleTemplateAbsolutePath   Absolute path to PHP template
     */
    public function __construct(
        $postType,
        $styleHandle,
        $styleUrl,
        $scriptHandle,
        $scriptUrl,
        $singleTemplateAbsolutePath
    ) {
        $this->postType = (string)$postType;
        $this->styleHandle = (string)$styleHandle;
        $this->styleUrl = (string)$styleUrl;
        $this->scriptHandle = (string)$scriptHandle;
        $this->scriptUrl = (string)$scriptUrl;
        $this->singleTemplateAbsolutePath = (string)$singleTemplateAbsolutePath;
    }

    public function registerHooks()
    {
        add_filter('single_template', [$this, 'filterSingleTemplate']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueSingleAssets']);
    }

    public function enqueueSingleAssets()
    {
        if (!is_singular($this->postType)) {
            return;
        }

        wp_enqueue_style(
            $this->styleHandle,
            $this->styleUrl,
            [],
            AMELIA_VERSION
        );

        wp_enqueue_script(
            $this->scriptHandle,
            $this->scriptUrl,
            [],
            AMELIA_VERSION,
            true
        );
    }

    /**
     * @param string $template
     *
     * @return string
     */
    public function filterSingleTemplate($template)
    {
        if (!is_singular($this->postType)) {
            return $template;
        }

        if (is_readable($this->singleTemplateAbsolutePath)) {
            return $this->singleTemplateAbsolutePath;
        }

        return $template;
    }
}
