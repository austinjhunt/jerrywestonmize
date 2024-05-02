<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

interface IRessio_Dispatcher
{
    const ORDER_FIRST = -5;
    const ORDER_STANDARD = 0;
    const ORDER_LAST = 5;

    /**
     * @param string[]|string $eventNames
     * @param array|string $callableObj
     * @param int $priority
     * @return void
     */
    public function addListener($eventNames, $callableObj, $priority = self::ORDER_STANDARD);

    /**
     * @param array|string $eventNames
     * @param array|string $callableObj
     * @return void
     */
    public function removeListener($eventNames, $callableObj);

    /**
     * @param array|string $eventNames
     * @return void
     */
    public function clearListeners($eventNames);

    /**
     * @param string $eventName
     * @param array $params
     * @return void
     */
    public function triggerEvent($eventName, $params = array());
}