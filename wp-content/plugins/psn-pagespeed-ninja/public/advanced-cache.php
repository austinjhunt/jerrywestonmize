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
defined('ABSPATH') || die();

if (!(
    defined('PAGESPEEDNINJA_CACHE_DIR') &&
    defined('PAGESPEEDNINJA_CACHE_PLUGIN') &&
    defined('PAGESPEEDNINJA_CACHE_RESSDIR') &&
    defined('PAGESPEEDNINJA_CACHE_DEPS_WEBP') &&
    defined('PAGESPEEDNINJA_CACHE_DEPS_AVIF') &&
    defined('PAGESPEEDNINJA_CACHE_DEPS_VENDOR') &&
    defined('PAGESPEEDNINJA_CACHE_TTL') &&
    defined('PAGESPEEDNINJA_CACHE_DISABLE_QUERIES') &&
    defined('PAGESPEEDNINJA_CACHE_PARAMS_SKIP') &&
    defined('PAGESPEEDNINJA_CACHE_COOKIES_DISABLE') &&
    defined('PAGESPEEDNINJA_CACHE_COOKIES_DEPEND') &&
    defined('PAGESPEEDNINJA_CACHE_HTTP_DEPEND') &&
    defined('PAGESPEEDNINJA_CACHE_EXCLUDE_URLS')
)) {
    return;
}

include_once dirname(__DIR__) . '/includes/class-pagespeedninja-cache.php';

global $pagespeedninja_cache;
if (!isset($pagespeedninja_cache)) {
    $pagespeedninja_cache = new PagespeedNinja_Cache();
}

if (function_exists('add_filter')) {
    $pagespeedninja_cache->register_hooks();
}
