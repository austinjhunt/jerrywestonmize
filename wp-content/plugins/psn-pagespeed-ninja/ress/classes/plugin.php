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
     */
    protected function loadConfig($filename, $override)
    {
        if (!is_file($filename)) {
            $this->di->logger->notice("File $filename not found");
            $this->params = $override ?: new stdClass();
        }
        $params = json_decode(file_get_contents($filename));
        if ($override !== null) {
            foreach ($override as $key => $value) {
                if (isset($params->$key) || property_exists($params, $key)) {
                    $params->$key = $value;
                } else {
                    $this->di->logger->notice("The key '$key' is not found in $filename");
                }
            }
        }
        $this->params = $params;
    }
}