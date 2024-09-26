<?php
/**
 * PageSpeed Ninja
 * https://pagespeed.ninja/
 *
 * @version    1.4.5
 * @license    GNU/GPL v2 - http://www.gnu.org/licenses/gpl-2.0.html
 * @copyright  (C) 2016-2024 PageSpeed Ninja Team
 * @date       September 2024
 */
defined('RESSIO_PATH') || die();

class Ressio_DeviceDetector_Wordpress extends Ressio_DeviceDetector_Base implements IRessio_DIAware, IRessio_DeviceDetector
{
    /**
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        $ua = $_SERVER['HTTP_USER_AGENT'];
        parent::__construct($ua);
    }

    /** @inheritdoc */
    public function screen_width()
    {
        return false;
    }

    /** @inheritdoc */
    public function screen_height()
    {
        return false;
    }

    /** @inheritdoc */
    public function screen_dpr()
    {
        return false;
    }

    /** @inheritdoc */
    public function browser_imgformats()
    {
        return null;
    }

    /** @inheritdoc */
    public function browser_js()
    {
        return true;
    }

    /** @inheritdoc */
    public function category()
    {
        return '';
    }

    /** @inheritdoc */
    public function isDesktop()
    {
        return true;
    }

    /** @inheritdoc */
    public function isMobile()
    {
        return false;
    }
}