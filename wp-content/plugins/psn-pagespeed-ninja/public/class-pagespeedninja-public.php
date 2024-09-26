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

class PagespeedNinja_Public
{
    /** @var string */
    private $plugin_name;

    /** @var string */
    private $version;

    /** @var bool */
    private $disabled = false;

    /** @var bool */
    private $started = false;

    /** @var Ressio */
    private $ressio;

    /** @var string */
    private $testKey = '';

    private $foundTime = 0;
    private $foundScripts = array();
    private $foundStyles = array();

    /**
     * @param string $plugin_name The name of the plugin.
     * @param string $version The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        if (isset($_GET['pagespeedninja'])) {
            switch ($_GET['pagespeedninja']) {
                case 'no':
                    define('DONOTCACHEPAGE', true);
                    $this->disabled = true;
                    $_COOKIE = array();
                    break;
                case 'desktop':
                    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453 Safari/537.36';
                    $_COOKIE = array();
                    break;
                case 'mobile':
                    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 8_3 like Mac OS X) AppleWebKit/537.36 (KHTML, like Gecko) Version/8.0 Mobile/12F70 Safari/600.1.4';
                    $_COOKIE = array();
                    break;
                case 'test':
                    define('DONOTCACHEPAGE', true);
                    $this->testKey = $_REQUEST['pagespeedninjakey'];
                    if (!preg_match('/^[0-9a-f]+$/i', $this->testKey)) {
                        wp_die();
                    }
                    // remove cookies to display guest page in backend
                    $_COOKIE = array();
                    break;
                default:
                    break;
            }
            unset($_GET['pagespeedninja'], $_REQUEST['pagespeedninja']);
        }
    }

    /** @return void */
    public function plugins_loaded()
    {
        if (
            $this->disabled
            || defined('XMLRPC_REQUEST') || defined('REST_REQUEST')
            || defined('DOING_AJAX') || defined('DOING_CRON')
            || defined('WP_ADMIN') || defined('WP_INSTALLING')
            || (defined('SHORTINIT') && SHORTINIT)
            || (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET')
            || isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            || isset($_GET['preview'])
            || isset($_GET['wp_scrape_key'])
            || isset($_GET['fl_builder']) || isset($_GET['mbuilder'])
            || is_favicon() || is_robots() || is_404() || is_admin() || is_feed() || is_comment_feed() || is_preview()
            || is_trackback() || is_customize_preview()
            || headers_sent()
        ) {
            $this->disabled = true;
            return;
        }

        $options = get_option('pagespeedninja_config');
        if ($options['afterinstall_popup'] !== '1') {
            $this->disabled = true;
            return;
        }

        ob_start(array($this, 'ob_callback'));
        $this->started = true;
    }

    /** @return void */
    public function template_redirect()
    {


        if ($this->disabled) {
            return;
        }

        $options = get_option('pagespeedninja_config');

        if ($options['psi_total-byte-weight']) {
            if ($options['js_merge']) {
                // Disable scripts concatenation by WordPress
                global $concatenate_scripts;
                $concatenate_scripts = false;
            }

            $emoji_priority = has_action('wp_head', 'print_emoji_detection_script');
            if ($emoji_priority !== false) {
                switch ($options['wp_mergewpemoji']) {
                    case 'merge':
                        remove_action('wp_head', 'print_emoji_detection_script', $emoji_priority);
                        add_action('wp_head', array($this, 'print_emoji_detection_script'), $emoji_priority);
                        break;
                    case 'disable':
                        remove_action('wp_head', 'print_emoji_detection_script', $emoji_priority);
                }
            }
        }
    }

    /**
     * @param array<string,array<string,string>> $wp_cache_meta
     * @return array
     */
    public function wp_cache_meta($wp_cache_meta)
    {
        // Support WP Super Cache
        if ($this->started && isset($this->ressio)) {
            foreach ($this->ressio->di->httpHeaders->getHeaders() as $header) {
                $key = substr($header, 0, strpos($header, ':') - 1);
                $wp_cache_meta['headers'][$key] = $header;
            }
        }

        return $wp_cache_meta;
    }

    /**
     * @param string $buffer
     * @return string|false
     * @throws ERessio_Exception
     * @throws ERessio_UnknownDiKey
     */
    public function ob_callback($buffer)
    {
        $buffer = ltrim($buffer);
        if (
            $this->disabled
            || $buffer === '' // empty page
            || (defined('DONOTMINIFY') && DONOTMINIFY) // disabled optimization
            || $buffer[0] !== '<' // bypass non-HTML (partials, json, etc.)
            || strncmp($buffer, '<?xml ', 6) === 0 // bypass XML (sitemap, etc.)
            || preg_match('/<html\s[^>]*?(?:⚡|\bamp\b)[^>]*>/u', $buffer) // bypass amp pages (detected by <html amp> or <html ⚡>)
        ) {
            return false;
        }

        /** @var array<string,string> $options */
        $options = get_option('pagespeedninja_config');

        // skip logged users
        if (!$options['enablelogged'] && is_user_logged_in()) {
            return false;
        }

        if ($this->testKey) {
            $filename = dirname(__DIR__) . '/admin/sessions/' . $this->testKey;
            if (is_file($filename)) {
                $override = file_get_contents($filename);
                $override = json_decode($override, true);
                foreach ($override as $name => $value) {
                    $options[$name] = $value;
                }
            }
            include_once dirname(__DIR__) . '/admin/class-pagespeedninja-admin-config.php';
            $ress_options = PagespeedNinja_AdminConfig::generateRessConfig($options);
        } else {
            $ress_options = json_decode($options['ress_options'], true);
        }

        $gzip = (bool)$options['html_gzip'];
        if ($gzip && (
                class_exists('W3_Plugin_TotalCache', false)
                || function_exists('check_richards_toolbox_gzip')
                || function_exists('wp_cache_phase2')
                || headers_sent()
                || in_array('ob_gzhandler', ob_list_handlers(), true)
            )) {
            $ress_options['html']['gzlevel'] = 0;
        }

        if ($options['psi_render-blocking-resources']) {
            if ($options['css_abovethefold'] && !empty($options['css_abovethefoldstyle'])) {
                $page = $_SERVER['REQUEST_URI'];
                $isHomepage = ($page === '/') || ($page === '/index.php');
                $isGlobal = (bool)$options['css_abovethefoldglobal'];
                if ($isHomepage || $isGlobal) {
                    $cacheEnabled = defined('WP_CACHE') && WP_CACHE;
                    $ress_options['plugins'][Ressio_Plugin_AboveTheFoldCSS::class] = array(
                        'cookie' => ($options['css_abovethefoldcookie'] && !$cacheEnabled) ? 'psn_visitor' : '',
                        'abovethefoldcss' => $options['css_abovethefoldstyle']
                    );
                    if (isset($ress_options['plugins'][Ressio_Plugin_InlineJsCss::class])) {
                        $ress_options['plugins'][Ressio_Plugin_InlineJsCss::class]['css'] = false;
                    }
                }
            }
        }

        if (!class_exists('Ressio', false)) {
            include_once dirname(__DIR__) . '/ress/ressio.php';
        }
        Ressio::registerAutoloading(true, PagespeedNinja::$classmap);

        // slightly improve performance by preloading some required Ressio's classes
        $ress_classes_dir = dirname(__DIR__) . '/ress/classes';
        include_once $ress_classes_dir . '/interfaces/dispatcher.php';
        include_once $ress_classes_dir . '/interfaces/diaware.php';
        include_once $ress_classes_dir . '/interfaces/cache.php';
        include_once $ress_classes_dir . '/interfaces/filesystem.php';
        include_once $ress_classes_dir . '/interfaces/filelock.php';
        include_once $ress_classes_dir . '/interfaces/htmloptimizer.php';
        include_once $ress_classes_dir . '/interfaces/htmlnode.php';
        include_once $ress_classes_dir . '/interfaces/csscombiner.php';
        include_once $ress_classes_dir . '/interfaces/cssminify.php';
        include_once $ress_classes_dir . '/interfaces/jscombiner.php';
        include_once $ress_classes_dir . '/interfaces/jsminify.php';
        include_once $ress_classes_dir . '/interfaces/httpcompressoutput.php';
        include_once $ress_classes_dir . '/interfaces/httpheaders.php';
        include_once $ress_classes_dir . '/di.php';
        include_once $ress_classes_dir . '/dispatcher.php';
        include_once $ress_classes_dir . '/helper.php';
        include_once $ress_classes_dir . '/urlrewriter.php';
        include_once $ress_classes_dir . '/filesystem/native.php';
        include_once $ress_classes_dir . '/filelock/flock.php';
        include_once $ress_classes_dir . '/htmloptimizer/base.php';
        include_once $ress_classes_dir . '/nodewrapper.php';
        include_once $ress_classes_dir . '/csscombiner.php';
        include_once $ress_classes_dir . '/jscombiner.php';

        $ressio = new Ressio($ress_options);
        $this->ressio = $ressio;

        /*
        if (!is_user_logged_in()) {
            $ttl = (int)$options['caching_ttl'] * 60;
            $ressio->di->httpHeaders->setHeaders(
                array(
                    'Expires: ' . gmdate('D, d M Y H:i:s', time() + $ttl) . ' GMT',
                    'Cache-Control: private, must-revalidate, max-age=' . $ttl
                )
            );
        }
        */

        if ($options['psi_total-byte-weight']) {
            $this->foundTime = time();


            $ressio->di->dispatcher->addListener('HtmlIterateTagSCRIPTBefore', array($this, 'collectScriptURLs'), -1);
            $ressio->di->dispatcher->addListener('HtmlIterateTagLINKBefore', array($this, 'collectStyleURLs'), -1);
        }

        if ($options['psi_total-byte-weight']) {
            if ($options['remove_objects']) {
                $ressio->di->dispatcher->addListener(
                    array(
                        'HtmlIterateTagOBJECTBefore',
                        'HtmlIterateTagEMBEDBefore',
                        'HtmlIterateTagAPPLETBefore'
                    ),
                    array($this, 'RessioRemoveTag')
                );
            }
        }

        $ressio->di->dispatcher->addListener('RunAfter', array($this, 'onRunAfter'));

        do_action('pagespeedninja_before_optimize', $ressio);

        $buffer = $ressio->run($buffer);

        global $pagespeedninja_cache;
        if ($pagespeedninja_cache !== null) {
            $this->tryPurgeCache($ressio);
        }

        Ressio::unregisterAutoloading();

        return $buffer;
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function RessioRemoveTag($event, $optimizer, $node)
    {
        $optimizer->nodeDetach($node);
    }

    /**
     * @param Ressio_Event $event
     * @param string $buffer
     * @return void
     */
    public function onRunAfter($event, $buffer)
    {
        do_action('pagespeedninja_cache_save', $buffer);

        global $wpdb;
        $time = date('Y-m-d H:i:s');

        $values = array();
        foreach (preg_replace('/[?#].*$/', '', $this->foundScripts) as $url) {
            if (isset($url[1])) { // skip empty and /
                $values[] = $wpdb->prepare('(%s, UNHEX(%s), %s, %d)', $url, sha1($url), $time, 1);
            }
        }
        foreach (preg_replace('/[?#].*$/', '', $this->foundStyles) as $url) {
            if (isset($url[1])) { // skip empty and /
                $values[] = $wpdb->prepare('(%s, UNHEX(%s), %s, %d)', $url, sha1($url), $time, 2);
            }
        }

        if (count($values)) {
            $sql = "INSERT IGNORE INTO `{$wpdb->prefix}psninja_urls` (`url`, `hash`, `time`, `type`) VALUES " . implode(',', $values) . ';';
            $wpdb->query($sql);
        }
    }

    /**
     * @param Ressio $ressio
     * @return void
     */
    protected function tryPurgeCache($ressio)
    {
        $filelock = $ressio->di->filelock;
        $fs = $ressio->di->filesystem;

        $lock = PAGESPEEDNINJA_CACHE_DIR . '/cachecleaner.stamp';

        if (!$fs->isFile($lock)) {
            $fs->touch($lock);
            return;
        }
        if (!$filelock->lock($lock)) {
            return;
        }

        $cache_timestamp = @filemtime(PAGESPEEDNINJA_CACHE_DIR . '/tags/GLOBAL');
        $aging_time = max($cache_timestamp, time() - PAGESPEEDNINJA_CACHE_TTL);
        if ($fs->getModificationTime($lock) > $aging_time) {
            $filelock->unlock($lock);
            return;
        }

        $fs->touch($lock);
        $filelock->unlock($lock);

        global $pagespeedninja_cache;
        $pagespeedninja_cache->purgeCache();
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function collectScriptURLs($event, $optimizer, $node)
    {
        if ($node->hasAttribute('src')) {
            $this->foundScripts[] = $node->getAttribute('src');
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function collectStyleURLs($event, $optimizer, $node)
    {
        if (
            $node->hasAttribute('rel') && $node->hasAttribute('href') &&
            $node->getAttribute('rel') === 'stylesheet'
        ) {
            $this->foundStyles[] = $node->getAttribute('href');
        }
    }

    /** @return void */
    public function wp_footer()
    {
        if (!$this->started) {
            return;
        }

        $template = __('Optimized with %s', 'psn-pagespeed-ninja');
        $options = get_option('pagespeedninja_config');
        if ($options['footer'] === '1') {
            echo '<small class="pagespeedninja" style="display:block;text-align:center">';
            printf($template, '<a href="https://pagespeed.ninja/" target="_blank">PageSpeed Ninja</a>');
            echo '</small>';
        } else {
            echo '<!--! ';
            printf($template, 'PageSpeed Ninja https://pagespeed.ninja/');
            echo ' -->';
        }
    }

    /** @return void */
    public function print_emoji_detection_script()
    {
        $settings = array(
            'baseUrl' => apply_filters('emoji_url', 'https://s.w.org/images/core/emoji/2.2.1/72x72/'),
            'ext' => apply_filters('emoji_ext', '.png'),
            'svgUrl' => apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/2.2.1/svg/'),
            'svgExt' => apply_filters('emoji_svg_ext', '.svg'),
        );

        $version = 'ver=' . get_bloginfo('version');
        $file = apply_filters('script_loader_src', includes_url("js/wp-emoji-release.min.js?$version"), 'concatemoji');

        ?><script defer>window._wpemojiSettings=<?php echo wp_json_encode($settings); ?>;</script><?php
        ?><script defer src="<?php echo esc_attr($file); ?>"></script><?php
    }
}
