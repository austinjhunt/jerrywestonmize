<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

/**
 * No CSS minification
 */
abstract class Ressio_CssMinify_Base implements IRessio_CssMinify, IRessio_DIAware
{
    /** @var Ressio_DI */
    public $di;
    /** @var Ressio_Config */
    public $config;

    /**
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        $this->di = $di;
        $this->config = $di->config;
    }

    /**
     * Minify CSS
     * @param string $str
     * @return string
     */
    public function minify($str)
    {
        return $str;
    }

    /**
     * Minify CSS in style=""
     * @param string $str
     * @param ?string $srcBase
     * @return string
     */
    public function minifyInline($str, $srcBase = null)
    {
        return $str;
    }
}