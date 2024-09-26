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

include_once dirname(__DIR__) . '/ress/classes/pagecache.php';

class PagespeedNinja_Cache extends Ressio_PageCache
{
    /**
     * @param bool $enable
     */
    public function __construct($enable = true)
    {
        if (!$enable) {
            $uri = null;
        } else {
            $uri = $_SERVER['REQUEST_URI'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $uri = strtok($uri, '?');
                parse_str($_SERVER['QUERY_STRING'], $query_list);
                $params_skip = explode("\n", PAGESPEEDNINJA_CACHE_PARAMS_SKIP);
                foreach ($params_skip as $param) {
                    unset($query_list[$param]);
                }
                if (count($query_list)) {
                    ksort($query_list, SORT_STRING);
                    $uri .= '?' . http_build_query($query_list);
                }
            }

            if ($uri === '/index.php') {
                $uri = '/';
            }
        }

        parent::__construct($uri, PAGESPEEDNINJA_CACHE_DIR, PAGESPEEDNINJA_CACHE_TTL, PAGESPEEDNINJA_CACHE_DEPS_VENDOR);
    }

    /** @return void */
    public function register_hooks()
    {
        if (!$this->caching) {
            return;
        }

        add_filter('wp_headers', array($this, 'save_headers'));
        add_filter('status_header', array($this, 'save_status'), 0, 2);
        add_action('pagespeedninja_cache_save', array($this, 'save'));
    }

    /**
     * @param array<string,string> $headers
     * @return string[]
     */
    public function save_headers($headers)
    {
        unset($headers['ETag']);

        $this->headers = array();
        foreach ($headers as $name => $value) {
            $this->headers[] = "$name: $value";
        }

        return $headers;
    }

    /**
     * @param string $status_header
     * @param int $code
     * @return string
     */
    public function save_status($status_header, $code)
    {
        // disable caching of errors and redirects
        if ($code !== 200) {
            $this->caching = false;
        }

        return $status_header;
    }

    /**
     * @param string $content
     * @return void
     */
    public function save($content)
    {
        if (
            !$this->caching
            || (defined('DONOTCACHEPAGE') && DONOTCACHEPAGE)
            || is_user_logged_in()
            || is_search()
        ) {
            return;
        }

        parent::save($content);
    }

    /**
     * @return bool
     */
    protected function disabledCaching()
    {
        return
            parent::disabledCaching() ||
            // other entry points or debug mode
            defined('DOING_AJAX') || defined('DOING_CRON') ||
            defined('WP_INSTALLING') ||
            defined('XMLRPC_REQUEST') || defined('REST_REQUEST') ||
            (defined('WP_ADMIN') && WP_ADMIN) ||
            (defined('WP_DEBUG') && WP_DEBUG) ||
            (defined('SHORTINIT') && SHORTINIT) ||

            // pagespeed-ninja disabled mode
            (isset($_GET['pagespeedninja']) && $_GET['pagespeedninja'] === 'no') ||
            // preview post
            isset($_GET['preview']) ||
            // WordPress file editor test
            isset($_GET['wp_scrape_key']) ||
            // Beaver Builder
            isset($_GET['fl_builder']) ||
            // Massive Dynamic Live Website Builder
            isset($_GET['mbuilder']) ||

            (PAGESPEEDNINJA_CACHE_DISABLE_QUERIES && !empty($_SERVER['QUERY_STRING'])) ||

            in_array($this->uri, explode("\n", PAGESPEEDNINJA_CACHE_EXCLUDE_URLS), true) ||

            $this->checkCookiesDisabled();
    }

    /**
     * @return bool
     */
    private function checkCookiesDisabled()
    {
        $cookies_disable = explode("\n", PAGESPEEDNINJA_CACHE_COOKIES_DISABLE);
        foreach ($cookies_disable as $key) {
            if (isset($_COOKIE[$key])) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string[]
     */
    protected function getRequestHashArray()
    {
        $hash_data = parent::getRequestHashArray();

        if (PAGESPEEDNINJA_CACHE_DEPS_WEBP) {
            $hash_data[] = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp')) ? 'webp' : '';
        }

        if (PAGESPEEDNINJA_CACHE_DEPS_AVIF) {
            $hash_data[] = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/avif')) ? 'avif' : '';
        }

        $cookies_depend = explode("\n", PAGESPEEDNINJA_CACHE_COOKIES_DEPEND);
        foreach ($cookies_depend as $name) {
            if (isset($_COOKIE[$name])) {
                $hash_data[] = $name . '=' . $_COOKIE[$name];
            }
        }

        $http_headers_depend = explode("\n", PAGESPEEDNINJA_CACHE_HTTP_DEPEND);
        foreach ($http_headers_depend as $name) {
            if (isset($_SERVER[$name])) {
                $hash_data[] = $name . '=' . $_SERVER[$name];
            }
        }


        return $hash_data;
    }

    /**
     * @return string
     */
    protected function getDeviceDependentHash()
    {
        include_once PAGESPEEDNINJA_CACHE_RESSDIR . '/classes/interfaces/devicedetector.php';
        include_once PAGESPEEDNINJA_CACHE_RESSDIR . '/classes/devicedetector/base.php';
        $detector = new Ressio_DeviceDetector_Base();

        $hash = $detector->vendor() . '@' . $this->wp_browser_detector();

        return $hash;
    }

    /**
     * @return string
     */
    protected function wp_browser_detector()
    {
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
            if (strpos($ua, 'Lynx') !== false) {
                return 'lynx';
            } elseif (strpos($ua, 'Edg') !== false) {
                return 'edge';
            } elseif (stripos($ua, 'chrome') !== false) {
                if (stripos($ua, 'chromeframe') !== false) {
                    return 'winIE';
                } else {
                    return 'chrome';
                }
            } elseif (stripos($ua, 'safari') !== false) {
                if (stripos($ua, 'mobile') !== false) {
                    return 'iphone';
                }
                return 'safari';
            } elseif ((strpos($ua, 'MSIE') !== false || strpos($ua, 'Trident') !== false) && strpos($ua, 'Win') !== false) {
                return 'winIE';
            } elseif (strpos($ua, 'MSIE') !== false && strpos($ua, 'Mac') !== false) {
                return 'macIE';
            } elseif (strpos($ua, 'Gecko') !== false) {
                return 'gecko';
            } elseif (strpos($ua, 'Opera') !== false) {
                return 'opera';
            } elseif (strpos($ua, 'Nav') !== false && strpos($ua, 'Mozilla/4.') !== false) {
                return 'NS4';
            }
        }
        return 'unknown';
    }

    protected function getAMDDOptions()
    {
    }
}
