<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

class Ressio_Event
{
    /** @var string */
    private $name;
    /** @var bool */
    private $stopped = false;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /** @return void */
    public function stop()
    {
        $this->stopped = true;
    }

    /**
     * @return bool
     */
    public function isStopped()
    {
        return $this->stopped;
    }
}
