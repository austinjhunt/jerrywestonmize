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
 * CSS minification
 */
class Ressio_CssMinify_Chain implements IRessio_CssMinify, IRessio_DIAware
{
    /** @var Ressio_DI */
    protected $di;

    /** @var Ressio_Config */
    protected $config;

    /** @var IRessio_CssMinify[] */
    protected $processors = array();

    /**
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        $this->di = $di;
        $this->config = $di->config;
        foreach ($this->config->css->minifychain as $className) {
            $this->processors[] = new $className($di);
        }
    }

    /**
     * Minify CSS
     * @param string $str
     * @return string
     * @throws ERessio_InvalidCss
     */
    public function minify($str)
    {
        foreach ($this->processors as $processor) {
            $str = $processor->minify($str);
        }
        return $str;
    }

    /**
     * Minify CSS in style=""
     * @param string $str
     * @param ?string $srcBase
     * @return string
     * @throws ERessio_InvalidCss
     */
    public function minifyInline($str, $srcBase = null)
    {
        foreach ($this->processors as $processor) {
            $str = $processor->minifyInline($str, $srcBase);
        }
        return $str;
    }
}