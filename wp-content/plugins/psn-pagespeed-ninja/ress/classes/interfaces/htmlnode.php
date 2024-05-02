<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

interface IRessio_HtmlNode
{
    /**
     * @return string
     */
    public function getTag();

    /**
     * @param string $name
     * @return bool
     */
    public function hasAttribute($name);

    /**
     * @param string $name
     * @return string
     */
    public function getAttribute($name);

    /**
     * @param string $name
     * @param string $value
     * @return void
     */
    public function setAttribute($name, $value);

    /**
     * @param string $name
     * @return void
     */
    public function removeAttribute($name);

    /**
     * @param string $class
     * @return void
     */
    public function addClass($class);

    /**
     * @param string $class
     * @return void
     */
    public function removeClass($class);
}
