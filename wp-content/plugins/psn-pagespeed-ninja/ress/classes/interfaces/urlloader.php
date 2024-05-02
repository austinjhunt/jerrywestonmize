<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

interface IRessio_UrlLoader
{
    /**
     * @param string $url
     * @param array $headers
     * @return Ressio_HTTPResponse|null
     */
    public function load($url, $headers);
}
