<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

interface IRessio_HttpCompressOutput
{
    /**
     * @param int $gzLevel Compression level
     * @param bool $autostart Set $this->compress as output handler
     * @return void
     */
    public function init($gzLevel, $autostart = true);

    /**
     * Content compressing by requesting method
     * @param string $content
     * @return string
     */
    public function compress($content);
}
