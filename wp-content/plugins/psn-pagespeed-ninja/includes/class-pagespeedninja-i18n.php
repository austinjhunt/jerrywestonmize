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

class PagespeedNinja_i18n
{
    /** @var string $domain The domain identifier for this plugin. */
    private $domain;

    /** @return void */
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain($this->domain);
    }

    /**
     * @param string $domain The domain that represents the locale of this plugin.
     * @return void
     */
    public function set_domain($domain)
    {
        $this->domain = $domain;
    }
}
