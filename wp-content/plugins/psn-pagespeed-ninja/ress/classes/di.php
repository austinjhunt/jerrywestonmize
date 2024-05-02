<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

/**
 * Note: this class is a Service Locator, not a real DI. Be careful with using it outside the Ressio project.
 *
 * @property-read IRessio_Cache $cache
 * @property-read Ressio_Config $config
 * @property-read IRessio_CssCombiner $cssCombiner
 * @property-read IRessio_CssMinify $cssMinify
 * @property-read IRessio_CssRelocator $cssRelocator
 * @property-read IRessio_Database $db
 * @property-read IRessio_DeviceDetector $deviceDetector
 * @property-read IRessio_Dispatcher $dispatcher
 * @property-read IRessio_Exec $exec
 * @property-read IRessio_FileLock $filelock
 * @property-read IRessio_Filesystem $filesystem
 * @property-read IRessio_HtmlOptimizer $htmlOptimizer
 * @property-read IRessio_HttpCompressOutput $httpCompressOutput
 * @property-read IRessio_HttpHeaders $httpHeaders
 * @property-read IRessio_ImgOptimizer $imgOptimizer
 * @property-read IRessio_JsCombiner $jsCombiner
 * @property-read IRessio_JsMinify $jsMinify
 * @property-read IRessio_Logger $logger
 * @property-read IRessio_UrlLoader $urlLoader
 * @property-read Ressio_UrlRewriter $urlRewriter
 * @property-read IRessio_Worker $worker
 */
#[AllowDynamicProperties]
class Ressio_DI
{
    private $_di = array();

    /**
     * @param string $key
     * @param string|array|object $value
     * @return void
     */
    public function set($key, $value)
    {
        $this->_di[$key] = $value;
        if (is_object($value)) {
            $this->{$key} = $value;
        } else {
            unset($this->{$key});
        }
    }

    /**
     * @param string $key
     * @return ?object
     * @throws ERessio_UnknownDiKey
     */
    public function __get($key)
    {
        if (!isset($this->_di[$key])) {
            throw new ERessio_UnknownDiKey('Unknown key: ' . $key);
        }

        if (isset($this->{$key})) {
            return $this->{$key};
        }

        return ($this->{$key} = $this->createNew($key));
    }

    /**
     * @param string $key
     * @return ?object
     * @throws ERessio_UnknownDiKey
     */
    public function get($key)
    {
        return $this->{$key};
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        // @Note: don't rename this method to __isset [it breaks unset(...) in the set() method]
        return isset($this->_di[$key]);
    }

    /**
     * @param string $key
     * @return ?object
     * @throws ERessio_UnknownDiKey
     */
    public function getNew($key)
    {
        if (!isset($this->_di[$key])) {
            throw new ERessio_UnknownDiKey('Unknown key: ' . $key);
        }

        return $this->createNew($key);
    }

    /**
     * @param string $key
     * @return string|array|object
     * @throws ERessio_UnknownDiKey
     */
    public function getEntry($key)
    {
        if (!isset($this->_di[$key])) {
            throw new ERessio_UnknownDiKey('Unknown key: ' . $key);
        }

        return $this->_di[$key];
    }

    /**
     * @param string $key
     * @return ?object
     */
    private function createNew($key)
    {
        $value = $this->_di[$key];

        if (is_string($value)) {
//            if (strpos($value, ':') === false) {
                // "classname"
                return new $value($this);
//            }
            // "classname:methodname"
//            list($className, $methodName) = explode(':', $value, 2);
//            return call_user_func(array($className, $methodName), $this);
        }

        if (is_object($value)) {
            if ($value instanceof Closure) {
                // function()
                return $value($this);
            }
            // object
            return $value;
        }
/*
        if (is_array($value)) {
            if (is_string($value[0])) {
                $className = $value[0];
                $params = isset($value[1]) ? (array)$value[1] : array();
                if (strpos($className, ':') === false) {
                    // array("classname", (array)options)
                    try {
                        return (new ReflectionClass($className))->newInstanceArgs($params);
                    } catch (ReflectionException $e) {
                        // don't use di->logger to avoid self-call
                        trigger_error('PageSpeed Ninja: reflection error ' . $e->getMessage());
                    }
                    return null;
                }
                // array("classname:methodname", (array)options)
                return call_user_func_array(explode(':', $className, 2), $params);
            }
            if (is_object($value[0])) {
                if ($value[0] instanceof Closure) {
                    // array(function(), option)
                    // @var Closure $fn
                    list($fn, $params) = $value;
                    return call_user_func($fn, $this, $params);
                }
                // array($obj, "methodname" [, (array)option])
                // @var object $obj
                list($obj, $methodName) = $value;
                $params = isset($value[2]) ? (array)$value[2] : array();
                return call_user_func_array(array($obj, $methodName), $params);
            }
        }
*/
        return null;
    }
}