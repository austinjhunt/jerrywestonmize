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
 * JS minification
 */
class Ressio_JsMinify_Chain implements IRessio_JsMinify, IRessio_DIAware
{
    /** @var Ressio_DI */
    protected $di;

    /** @var Ressio_Config */
    protected $config;

    /** @var IRessio_JsMinify[] */
    protected $processors = array();

    /**
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        $this->di = $di;
        $this->config = $di->config;
        foreach ($this->config->js->minifychain as $className) {
            $this->processors[] = new $className($di);
        }
    }

    /**
     * Minify JS
     * @param string $str
     * @return string
     * @throws ERessio_InvalidJs
     */
    public function minify($str)
    {
        foreach ($this->processors as $processor) {
            $str = $processor->minify($str);
        }
        return $str;
    }

    /**
     * Minify JS in onevent=""
     * @param string $str
     * @return string
     * @throws ERessio_InvalidJs
     */
    public function minifyInline($str)
    {
        foreach ($this->processors as $processor) {
            $str = $processor->minifyInline($str);
        }
        return $str;
    }
}