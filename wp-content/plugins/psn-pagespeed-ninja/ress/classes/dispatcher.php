<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Dispatcher implements IRessio_Dispatcher, IRessio_DIAware
{
    /** @var array */
    private $listeners = array();
    /** @var int */
    private $counter = 0;

    /**
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
    }

    /**
     * @param string[]|string $eventNames
     * @param array|string $callableObj
     * @param int $priority
     * @return void
     * @throws ERessio_InvalidEventName
     */
    public function addListener($eventNames, $callableObj, $priority = self::ORDER_STANDARD)
    {
        if (is_array($eventNames)) {
            foreach ($eventNames as $eventName) {
                $this->addListener($eventName, $callableObj, $priority);
            }
        } elseif (is_string($eventNames)) {
            $this->counter++;
            $key = $priority * (1 << 24) + $this->counter;
            if (isset($this->listeners[$eventNames])) {
                $this->listeners[$eventNames][$key] = $callableObj;
                ksort($this->listeners[$eventNames], SORT_NUMERIC);
            } else {
                $this->listeners[$eventNames] = array($key => $callableObj);
            }
        } else {
            throw new ERessio_InvalidEventName('Incorrect event name in addListener');
        }
    }

    /**
     * @param array|string $eventNames
     * @param array|string $callableObj
     * @return void
     * @throws ERessio_InvalidEventName
     */
    public function removeListener($eventNames, $callableObj)
    {
        if (is_array($eventNames)) {
            foreach ($eventNames as $eventName) {
                $this->removeListener($eventName, $callableObj);
            }
        } elseif (is_string($eventNames)) {
            if (is_array($this->listeners[$eventNames])) {
                foreach ($this->listeners[$eventNames] as $i => $listener) {
                    if ($listener === $callableObj) {
                        unset($this->listeners[$eventNames][$i]);
                    }
                }
            }
        } else {
            throw new ERessio_InvalidEventName('Incorrect event name in removeListener');
        }
    }

    /**
     * @param array|string $eventNames
     * @return void
     * @throws ERessio_InvalidEventName
     */
    public function clearListeners($eventNames)
    {
        if (is_array($eventNames)) {
            foreach ($eventNames as $eventName) {
                $this->clearListeners($eventName);
            }
        } elseif (is_string($eventNames)) {
            unset($this->listeners[$eventNames]);
        } else {
            throw new ERessio_InvalidEventName('Incorrect event name in clearListeners');
        }
    }

    /**
     * @param string $eventName
     * @param array $params
     * @return void
     */
    public function triggerEvent($eventName, $params = array())
    {
        if (isset($this->listeners[$eventName])) {
            $event = new Ressio_Event($eventName);
            $Args = array($event);
            // Trick from http://php.net/manual/en/function.call-user-func-array.php#91503
            foreach ($params as &$arg) {
                $Args[] = &$arg;
            }
            foreach ($this->listeners[$eventName] as $listener) {
                call_user_func_array($listener, $Args);
                if ($event->isStopped()) {
                    break;
                }
            }
        }
    }
}