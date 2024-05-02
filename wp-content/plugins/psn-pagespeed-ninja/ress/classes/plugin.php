<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Plugin
{
    /** @var Ressio_DI */
    protected $di;
    /** @var Ressio_Config */
    protected $config;

    /** @var stdClass */
    public $params;

    /**
     * @param Ressio_DI $di
     * @param ?stdClass $params
     */
    public function __construct($di, $params = null)
    {
        $this->di = $di;
        $this->config = $di->config;
        $this->params = $params;
    }

    /**
     * @return array
     */
    public function getEventPriorities()
    {
        return array();
    }

    /**
     * @param string $filename
     * @param ?stdClass $override
     * @return stdClass
     */
    protected function loadConfig($filename, $override)
    {
        if (!is_file($filename)) {
            return $override ?: new stdClass();
        }
        $params = json_decode(file_get_contents($filename));
        if ($override !== null) {
            foreach ($override as $key => $value) {
                // Note: this method is usually called before constructor, so $this->>di is not available to log messages
                //if (isset($params->$key) || property_exists($params, $key)) {
                $params->$key = $value;
                //}
            }
        }
        return $params;
    }
}