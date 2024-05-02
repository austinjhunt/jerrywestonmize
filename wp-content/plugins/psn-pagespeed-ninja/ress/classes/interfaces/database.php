<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

interface IRessio_Database
{
    /**
     * @param string $str
     * @return string
     */
    public function quote($str);

    /**
     * @param string $sql
     * @return void
     */
    public function query($sql);

    /**
     * @param string $sql
     * @return mixed|null
     */
    public function loadValue($sql);

    /**
     * @param string $sql
     * @return stdClass|null
     */
    public function loadRow($sql);

    /**
     * @param string $sql
     * @return stdClass[]|null
     */
    public function loadRows($sql);

    /**
     * @param string $sql
     * @return array
     */
    public function loadColumn($sql);
}
