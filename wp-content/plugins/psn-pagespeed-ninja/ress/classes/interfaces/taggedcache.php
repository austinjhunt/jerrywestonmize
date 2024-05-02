<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

interface IRessio_Taggedcache
{
    /**
     * @param string $key
     * @param string $data
     * @param array $tags
     * @return bool
     */
    public function save($key, $data, $tags);

    /**
     * @param string $key
     * @return ?string
     */
    public function get($key);

    /**
     * @param string $key
     * @return bool
     */
    public function has($key);

    /**
     * @param string $key
     * @return void
     */
    public function delete($key);

    /**
     * @return void
     */
    public function clear();

    /**
     * @param string $tag
     * @return void
     */
    public function invalidateTag($tag);

    /**
     * @param array $tags
     * @return void
     */
    public function invalidateTags($tags);
}