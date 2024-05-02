<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

/**
 * CSS minification interface
 * @todo Do we need srcBase/targetBase (seems they are taken by cssRelocator now)
 */
interface IRessio_CssMinify
{
    /**
     * Minify CSS
     * @param string $str
     * @return string
     * @throws ERessio_InvalidCss
     */
    public function minify($str);

    /**
     * Minify CSS in style=""
     * @param string $str
     * @param ?string $srcBase
     * @return string
     * @throws ERessio_InvalidCss
     */
    public function minifyInline($str, $srcBase = null);
}