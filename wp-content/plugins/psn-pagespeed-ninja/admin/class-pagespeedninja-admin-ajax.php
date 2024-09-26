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

class PagespeedNinja_AdminAjax
{
    /** @var PagespeedNinja_Admin */
    private $admin;

    /**
     * Initialize the class and set its properties.
     *
     * @param PagespeedNinja_Admin $plugin_admin
     */
    public function __construct($plugin_admin)
    {
        $this->admin = $plugin_admin;
    }

    /** @return void */
    public function get_cache_size()
    {
        check_ajax_referer('psn-ajax-token');

        header('Content-Type: text/json');

        $type = $_POST['type'];

        switch ($type) {
            case 'image':
                $size = 0;
                $files = 0;
                foreach ($this->getStaticDirs() as $staticdir) {
                    $dir = rtrim(ABSPATH, '/') . $staticdir;
                    $this->getDirectoryStats($dir . '/img', $size1, $files1);
                    $this->getDirectoryStats($dir . '/img-r', $size2, $files2);
                    $this->getDirectoryStats($dir . '/img-lqip', $size2, $files2);
                    $size += $size1 + $size2;
                    $files += $files1 + $files2;
                }
                echo json_encode(array('size' => size_format($size), 'files' => $files));
                break;
            case 'loaded':
                $size = 0;
                $files = 0;
                foreach ($this->getStaticDirs() as $staticdir) {
                    $dir = rtrim(ABSPATH, '/') . $staticdir;
                    $this->getDirectoryStats($dir . '/loaded', $size1, $files1);
                    $size += $size1;
                    $files += $files1;
                }
                echo json_encode(array('size' => size_format($size), 'files' => $files));
                break;
            case 'static':
                $size = 0;
                $files = 0;
                foreach ($this->getStaticDirs() as $staticdir) {
                    $dir = rtrim(ABSPATH, '/') . $staticdir;
                    $this->getDirectoryStats($dir, $size1, $files1, false);
                    $size += $size1;
                    $files += $files1;
                }
                echo json_encode(array('size' => size_format($size), 'files' => $files));
                break;
            case 'ress':
                $resscachedir = WP_CONTENT_DIR . '/uploads/psn-pagespeed-ninja/cache';
                $this->getDirectoryStats($resscachedir, $size, $files);
                echo json_encode(array('size' => size_format($size), 'files' => $files));
                break;
            case 'page':
                $pagecachedir = WP_CONTENT_DIR . '/uploads/psn-pagespeed-ninja/pagecache';
                $this->getDirectoryStats($pagecachedir, $size, $files);
                echo json_encode(array('size' => size_format($size), 'files' => $files));
                break;
        }
        wp_die();
    }

    /**
     * @param string $dir
     * @param int $size
     * @param int $files
     * @param bool $recursive
     * @return void
     */
    protected function getDirectoryStats($dir, &$size, &$files, $recursive = true)
    {
        $size = 0;
        $files = 0;

        if (is_dir($dir)) {
            $this->getDirectorySize($dir, $size, $files, $recursive);
        }
    }

    /**
     * @param string $dir
     * @param int $size
     * @param int $files
     * @param bool $recursive
     * @return void
     */
    private function getDirectorySize($dir, &$size, &$files, $recursive = true)
    {
        if ($h = @opendir($dir)) {
            while ($entry = readdir($h)) {
                if ($entry !== '.' && $entry !== '..') {
                    $path = $dir . DIRECTORY_SEPARATOR . $entry;
                    if (is_file($path)) {
                        if (!is_link($path)) {
                            $size += filesize($path);
                        }
                        $files++;
                    } elseif ($recursive && is_dir($path)) {
                        $this->getDirectorySize($path, $size, $files);
                    }
                }
            }
            closedir($h);
        }
    }

    /**
     * @param string $dir
     * @param bool $recursive
     * @return void
     */
    protected function clearDirectory($dir, $recursive = true)
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir, SCANDIR_SORT_NONE);
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..' && $entry !== '.htaccess') {
                $path = $dir . DIRECTORY_SEPARATOR . $entry;
                if (is_file($path) || is_link($path)) {
                    unlink($path);
                } elseif ($recursive) {
                    $this->clearDirectory($path);
                    rmdir($path);
                }
            }
        }
    }

    /**
     * @param string $dir
     * @param bool $recursive
     * @return void
     */
    protected function clearDirectoryLinks($dir, $recursive = true)
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir, SCANDIR_SORT_NONE);
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..' && $entry !== '.htaccess') {
                $path = $dir . DIRECTORY_SEPARATOR . $entry;
                if (is_file($path) && is_link($path)) {
                    unlink($path);
                } elseif ($recursive && is_dir($path)) {
                    $this->clearDirectoryLinks($path);
                    @rmdir($path);
                }
            }
        }
    }

    /**
     * @return string[]
     */
    protected function getStaticDirs()
    {
        if (!is_multisite()) {
            $config = get_option('pagespeedninja_config');
            return array($config['staticdir']);
        }
        /** @var array<string,int> $staticdirs */
        $staticdirs = array();
        foreach (get_sites() as $site) {
            $config = get_blog_option($site->blog_id, 'pagespeedninja_config');
            $staticdirs[$config['staticdir']] = 1;
        }
        return array_keys($staticdirs);
    }

    /** @return void */
    public function clear_images()
    {
        check_ajax_referer('psn-ajax-token');

        foreach ($this->getStaticDirs() as $staticdir) {
            $dir = rtrim(ABSPATH, '/') . $staticdir;
            $this->clearDirectory($dir . '/img');
            $this->clearDirectory($dir . '/img-r');
            $this->clearDirectory($dir . '/img-lqip');
        }
        wp_die();
        exit;
    }

    /** @return void */
    public function clear_image_errors()
    {
        check_ajax_referer('psn-ajax-token');

        foreach ($this->getStaticDirs() as $staticdir) {
            $dir = rtrim(ABSPATH, '/') . $staticdir;
            $this->clearDirectoryLinks($dir . '/img');
            $this->clearDirectoryLinks($dir . '/img-r');
            $this->clearDirectoryLinks($dir . '/img-lqip');
        }
        wp_die();
        exit;
    }

    /** @return void */
    public function clear_loaded()
    {
        check_ajax_referer('psn-ajax-token');

        foreach ($this->getStaticDirs() as $staticdir) {
            $dir = rtrim(ABSPATH, '/') . $staticdir;
            $this->clearDirectory($dir . '/loaded');
        }
        wp_die();
        exit;
    }

    /**
     * @param int $ttl (seconds)
     * @return void
     */
    protected function clear_cache($ttl)
    {

        /** @var array<string,string> $options */
        $options = get_option('pagespeedninja_config');

        if (!preg_match('#^/[^/]#', $options['staticdir'])) {
            return;
        }

        if (!class_exists('Ressio', false)) {
            include_once dirname(__DIR__) . '/ress/ressio.php';
        }

        try {
            Ressio::registerAutoloading(true);
        } catch (Exception $e) {
            return;
        }

        // invalidate page cache (empty cache triggers /s clearing)
        $pagecache_stamp = WP_CONTENT_DIR . '/uploads/psn-pagespeed-ninja/pagecache/tags/GLOBAL';
        if (file_exists($pagecache_stamp)) {
            $newStamp = time() - $ttl;
            if (@filemtime($pagecache_stamp) < $newStamp) {
                touch($pagecache_stamp, $newStamp);
            }
        } else {
            touch($pagecache_stamp);
        }

        // @TODO rewrite using CacheCleaner class instead of Ressio_Plugin_FilecacheCleaner plugin
        try {
            $di = new Ressio_DI();
            $di->set('config', new stdClass());
            $di->config->cachedir = WP_CONTENT_DIR . '/uploads/psn-pagespeed-ninja/cache';
            $di->config->cachettl = $ttl;
            $di->config->webrootpath = rtrim(ABSPATH, '/');
            $di->config->staticdir = $options['staticdir'];
            $di->config->use_symlink = true;
            $di->config->change_group = null;
            $di->set('filesystem', Ressio_Filesystem_Native::class);
            $di->set('filelock', Ressio_FileLock_flock::class);

            $lock = $di->config->cachedir . '/filecachecleaner.stamp';
            @unlink($lock);

            Ressio_CacheCleaner::clean($di);
        } catch (ERessio_UnknownDiKey $e) {
            return;
        }
    }

    /** @return void */
    public function clear_cache_expired()
    {
        check_ajax_referer('psn-ajax-token');

        /** @var array<string,string> $options */
        $options = get_option('pagespeedninja_config');
        $ttl = (int)$options['caching_ttl'] * 60;
        $this->clear_cache($ttl);
        wp_die();
        exit;
    }

    /** @return void */
    public function clear_cache_all()
    {
        check_ajax_referer('psn-ajax-token');

        $this->clear_cache(1);
        wp_die();
        exit;
    }

    /**
     * @param int $ttl (seconds)
     * @return void
     */
    protected function clear_pagecache($ttl)
    {
        global $pagespeedninja_cache;
        if (!isset($pagespeedninja_cache)) {
            if (!defined('PAGESPEEDNINJA_CACHE_DIR')) {
                define('PAGESPEEDNINJA_CACHE_DIR', WP_CONTENT_DIR . '/uploads/psn-pagespeed-ninja/pagecache');
            }
            if (!defined('PAGESPEEDNINJA_CACHE_TTL')) {
                define('PAGESPEEDNINJA_CACHE_TTL', $ttl);
            }
            $pluginDir = dirname(__DIR__);
            include $pluginDir . '/public/advanced-cache.php';
        }
        $pagespeedninja_cache->purgeCache($ttl);
    }

    /** @return void */
    public function clear_pagecache_expired()
    {
        check_ajax_referer('psn-ajax-token');

        /** @var array<string,string> $options */
        $options = get_option('pagespeedninja_config');
        $ttl = (int)$options['caching_ttl'] * 60;
        $this->clear_pagecache($ttl);
        wp_die();
        exit;
    }

    /** @return void */
    public function clear_pagecache_all()
    {
        check_ajax_referer('psn-ajax-token');

        $this->clear_pagecache(0);
        wp_die();
        exit;
    }

    /** @return void */
    public function update_atfcss()
    {
        check_ajax_referer('psn-ajax-token');

        /** @var array $options */
        $options = get_option('pagespeedninja_config');

        $options['css_abovethefoldstyle'] = '';
        update_option('pagespeedninja_config', $options);

        $atfCSS = $this->loadATFCSS($options);
        $plugin_file = dirname(__DIR__) . '/pagespeedninja.php';
        if ($atfCSS !== '' && is_file($plugin_file)) {
            $options['css_abovethefoldstyle'] = $atfCSS;
            update_option('pagespeedninja_config', $options);
        }

        wp_die();
        exit;
    }

    /** @return void */
    public function ajax_key()
    {
        check_ajax_referer('psn-ajax-token');

        $config = $_POST['pagespeedninja_config'];

        $json = json_encode($config);
        $key = sha1($json . NONCE_SALT);

        file_put_contents(__DIR__ . '/sessions/' . $key, $json, LOCK_EX);

        echo $key;
        wp_die();
    }

    /**
     * @param array<string,string> $options
     * @return string
     */
    private function loadATFCSS($options)
    {

        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $websiteURL = rtrim(get_option('home'), '/') . '/?pagespeedninja=no';

        $data = array(
            'url' => $websiteURL,
            'apikey' => $options['apikey'],
        );

        $tmp_filename = download_url('https://api.pagespeed.ninja/v1/getcss?' . http_build_query($data), 60);
        if (is_string($tmp_filename)) {
            $css = @file_get_contents($tmp_filename);
            @unlink($tmp_filename);
            return $css;
        }
        return '';
    }

    /** @return void */
    public function dismiss_licensekey_notice()
    {
        check_ajax_referer('psn-ajax-token');

        set_transient('pagespeedninja_dismiss_licensekey_notice', '1', 7 * 24 * 60 * 60);

        wp_die();
    }

    /** @return void */
    public function save_psi_scores()
    {
        check_ajax_referer('psn-ajax-token');

        $data = array(
            'desktop_orig' => $_POST['pagespeed_desktop_orig'],
            'mobile_orig' => $_POST['pagespeed_mobile_orig'],
            'desktop' => $_POST['pagespeed_desktop'],
            'mobile' => $_POST['pagespeed_mobile'],
        );
        set_transient('pagespeedninja_psi_scores', $data);

        wp_send_json_success();
    }
}
