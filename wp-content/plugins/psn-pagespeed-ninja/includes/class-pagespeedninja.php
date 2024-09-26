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

class PagespeedNinja
{
    /** @var string $plugin_slug Official slug for this plugin on wordpress.org. */
    protected $plugin_slug;

    /** @var string $plugin_name The string used to uniquely identify this plugin. */
    protected $plugin_name;

    /** @var string $version The current version of the plugin. */
    protected $version;

    /** @var string $plugin_dir_path Path to plugin files */
    protected $plugin_dir_path;

    /** @var array<string,string> $options Plugin settings */
    protected $options;

    /** @var array<string,string> */
    public static $classmap = array();

    /**
     * @param string $plugin_slug
     * @param string $plugin_name
     */
    public function __construct($plugin_slug, $plugin_name)
    {
        $this->plugin_slug = $plugin_slug;
        $this->plugin_name = $plugin_name;
        $this->version = '1.4.5';
        $this->plugin_dir_path = plugin_dir_path(__DIR__);

        self::$classmap = array(
            Ressio_DeviceDetector_Wordpress::class => $this->plugin_dir_path . '/public/ress/wpdevicedetector.php'
        );
    }

    /**
     * @return string Official slug of the plugin.
     */
    public function get_plugin_slug()
    {
        return $this->plugin_slug;
    }

    /**
     * @return string The name of the plugin.
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * @return string The version of the plugin
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
     * @param bool $network_wide
     * @return void
     * @throws ERessio_Exception
     * @throws ERessio_UnknownDiKey
     */
    public function activate($network_wide)
    {
        require_once $this->plugin_dir_path . 'includes/class-pagespeedninja-activator.php';

        $plugin_slug = $this->get_plugin_slug();
        $plugin_name = $this->get_plugin_name();
        $version = $this->get_version();

        PagespeedNinja_Activator::ping(1, $version);
        if ($network_wide && is_multisite()) {
            PagespeedNinja_Activator::activate_network($plugin_slug, $plugin_name, $version);
            foreach (get_sites() as $site) {
                switch_to_blog($site->blog_id);
                PagespeedNinja_Activator::activate_site($plugin_slug, $plugin_name, $version);
                restore_current_blog();
            }
        } else {
            PagespeedNinja_Activator::activate_site($plugin_slug, $plugin_name, $version);
        }
    }

    /**
     * @param bool $network_deactivating
     * @return void
     */
    public function deactivate($network_deactivating)
    {
        require_once $this->plugin_dir_path . 'includes/class-pagespeedninja-activator.php';

        $plugin_slug = $this->get_plugin_slug();
        $plugin_name = $this->get_plugin_name();
        $version = $this->get_version();

        PagespeedNinja_Activator::ping(2, $version);
        if ($network_deactivating && is_multisite()) {
            PagespeedNinja_Activator::deactivate_network($plugin_slug, $plugin_name, $version);
            foreach (get_sites() as $site) {
                switch_to_blog($site->blog_id);
                PagespeedNinja_Activator::deactivate_site($plugin_slug, $plugin_name, $version);
                restore_current_blog();
            }
        } else {
            PagespeedNinja_Activator::deactivate_site($plugin_slug, $plugin_name, $version);
        }

        $files = array(
            ABSPATH . 'wp-includes/.htaccess',
            ABSPATH . 'wp-content/.htaccess',
            ABSPATH . 'uploads/.htaccess'
        );
        $marker = 'Page Speed Ninja';
        foreach ($files as $file) {
            if (is_file($file)) {
                insert_with_markers($file, $marker, '');
            }
        }

        if (!empty($_POST)) {
            $this->send_survey();
        }
    }

    /** @return void */
    private function send_survey()
    {
        $data = $_POST;
        $option = isset($data['psn-survey-option']) ? (int)$data['psn-survey-option'] : 0;
        $reason_key = 'psn-reason' . $option;
        $reason = isset($data[$reason_key]) ? $data[$reason_key] : '';
        wp_safe_remote_post(
            'https://api.pagespeed.ninja/v1/survey-deactivate',
            array(
                'body' => array('option' => $option, 'reason' => $reason),
                'timeout' => 1,
                'connect_timeout' => 5,
            )
        );
    }

    /**
     * @param int $blog_id
     * @param int $user_id
     * @param string $domain
     * @param string $path
     * @param int $site_id
     * @param array $meta
     * @return void
     * @throws ERessio_Exception
     * @throws ERessio_UnknownDiKey
     */
    public function wpmu_new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta)
    {
        switch_to_blog($blog_id);
        PagespeedNinja_Activator::activate_site($this->get_plugin_slug(), $this->get_plugin_name(), $this->get_version());
        restore_current_blog();
    }

    /** @return void */
    public function run()
    {
        add_action('upgrader_process_complete', array($this, 'upgrader_process_complete'), 10, 2);
        add_action('pagespeedninja_daily_event', array($this, 'cron_daily'));
        add_action('plugins_loaded', array($this, 'init'));
        add_action('wpmu_new_blog', array($this, 'wpmu_new_blog'));
        add_action('admin_bar_menu', array($this, 'admin_bar_menu'), 100);

        $this->set_locale();
        $this->define_config_hooks();
        $this->define_cache_hooks();
        if (is_admin()) {
            $this->define_admin_hooks();
        } else {
            $this->define_public_hooks();
        }
        if (file_exists($this->plugin_dir_path . 'pro/class-pagespeedninja-pro.php')) {
            require_once $this->plugin_dir_path . 'pro/class-pagespeedninja-pro.php';
            $plugin_pro = new PagespeedNinja_Pro($this->get_plugin_slug(), $this->get_plugin_name(), $this->get_version());
            $plugin_pro->run();
        }
    }

    /** @return void */
    public function init()
    {
        $this->options = get_option('pagespeedninja_config');

        if (!isset($this->options['version']) || $this->options['version'] !== $this->get_version()) {
            $this->update_config();
        }
    }

    /**
     * @param Plugin_Upgrader $upgrader_object
     * @param array<string,mixed> $upgrader_options
     * @return void
     */
    public function upgrader_process_complete($upgrader_object, $upgrader_options)
    {
        if (
            isset($upgrader_options['type'], $upgrader_options['plugins']) &&
            $upgrader_options['type'] === 'plugin' &&
            in_array("{$this->plugin_slug}/{$this->plugin_name}.php", $upgrader_options['plugins'], true)
        ) {
            $this->update_config();
        }
    }

    /** @return void */
    public function cron_daily()
    {
        require_once __DIR__ . '/class-pagespeedninja-activator.php';
        PagespeedNinja_Activator::schedule_next_daily_event();



        /** @var array $options */
        $options = get_option('pagespeedninja_config');

        $plugin_file = dirname(__DIR__) . '/pagespeedninja.php';
        $session_dir = dirname(__DIR__) . '/admin/sessions';

        // update Above-the-fold CSS
        if ($options['allow_ext_atfcss'] === '1' && $options['psi_unused-css-rules'] && $options['css_abovethefold'] && $options['css_abovethefoldautoupdate']) {
            $atfCSS = $this->loadATFCSS();
            if ($atfCSS !== '' && is_file($plugin_file)) {
                $options['css_abovethefoldstyle'] = $atfCSS;
                update_option('pagespeedninja_config', $options);
            }
        }

        if (!is_file($plugin_file)) {
            // The plugin has been uninstalled
            return;
        }

        // clear sessions
        $h = opendir($session_dir);
        while (($file = readdir($h)) !== false) {
            /** @var string $file */
            $file_path = $session_dir . '/' . $file;
            if ($file[0] === '.' || !is_file($file_path)) {
                continue;
            }
            if (filemtime($file_path) < time() - 24 * 60 * 60) {
                unlink($file_path);
            }
        }
        closedir($h);

        // clear RESS cache
        if (!class_exists('Ressio', false)) {
            include_once dirname(__DIR__) . '/ress/ressio.php';
        }
        Ressio::registerAutoloading(true, self::$classmap);

        if (preg_match('#^/[^/]#', $options['staticdir'])) {
            $di = new Ressio_DI();
            $di->set('config', new stdClass());
            $di->config->cachedir = WP_CONTENT_DIR . '/uploads/psn-pagespeed-ninja/cache';
            $di->config->cachettl = max(24 * 60, (int)$options['caching_ttl']) * 60;
            $di->config->webrootpath = rtrim(ABSPATH, '/');
            $di->config->staticdir = $options['staticdir'];
            $di->config->use_symlink = true;
            $di->config->change_group = null;
            $di->set('filesystem', Ressio_Filesystem_Native::class);
            $di->set('filelock', Ressio_FileLock_flock::class);
            Ressio_CacheCleaner::clean($di);
        }
    }

    /** @return void */
    public function admin_bar_menu()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $options = get_option('pagespeedninja_config');
        if ($options['afterinstall_popup'] !== '1' && !is_network_admin()) {
            return;
        }

        wp_register_style('pagespeedninja_adminbar_style', plugins_url('/assets/css/pagespeedninja-adminbar.css', __DIR__),
            array(), $this->version);
        wp_register_script('pagespeedninja_adminbar_dummy_script', '', array('jquery'));

        wp_enqueue_style('pagespeedninja_adminbar_style');
        wp_enqueue_script('pagespeedninja_adminbar_dummy_script');

        $ajaxNonce = wp_json_encode(wp_create_nonce('psn-ajax-token'));
        wp_add_inline_script('pagespeedninja_adminbar_dummy_script',
<<<END
(function () {
    function doAjax(e, action) {
        e.preventDefault();
        jQuery.post(ajaxurl, {action: action, _ajax_nonce: $ajaxNonce});
        jQuery('#wp-admin-bar-pagespeed-ninja').removeClass('hover');
    }
    jQuery(document).ready(function () {
        jQuery('#wp-admin-bar-pagespeed-ninja-purge-pagecache a').on('click', function (e) {
            doAjax(e, 'pagespeedninja_clear_pagecache_all');
        });
        jQuery('#wp-admin-bar-pagespeed-ninja-update-atfcss a').on('click', function (e) {
            doAjax(e, 'pagespeedninja_update_atfcss');
        });
    });
})();
END
        );

        global $wp_admin_bar;

        $wp_admin_bar->add_node(
            array(
                'id' => 'pagespeed-ninja',
                'title' =>
                    '<span class="ab-icon" aria-hidden="true"></span>' .
                    '<span class="ab-label">' . __('PageSpeed Ninja', 'psn-pagespeed-ninja') . '</span>',
            )
        );

        $wp_admin_bar->add_node(
            array(
                'id' => 'pagespeed-ninja-purge-pagecache',
                'title' => __('Purge Page Cache', 'psn-pagespeed-ninja'),
                'parent' => 'pagespeed-ninja',
                'href' => '#',
            )
        );

        $node = array(
            'id' => 'pagespeed-ninja-update-atfcss',
            'title' => __('Update Critical CSS', 'psn-pagespeed-ninja'),
            'parent' => 'pagespeed-ninja',
        );
        if (!is_network_admin() && $options['allow_ext_atfcss'] === '1' && $options['psi_unused-css-rules'] && $options['css_abovethefold']) {
            $node['href'] = '#';
        }
        $wp_admin_bar->add_node($node);

        if (!apply_filters('psn_is_pro', false)) {
            $url = 'https://pagespeed.ninja/download/?utm_source=psnbackend&amp;utm_medium=Menubar-upgrade&amp;utm_campaign=Admin-upgrade';
            $wp_admin_bar->add_node(
                array(
                    'id' => 'pagespeed-ninja-upgrade-to-pro',
                    'title' =>
                        '<span onclick="window.open(\'' . $url . '\', \'_blank\').focus();">' .
                        __('Upgrade to Pro', 'psn-pagespeed-ninja') .
                        '</span>',
                    'parent' => 'pagespeed-ninja',
                    'href' => $url,
                )
            );
        }
    }

    /**
     * @return string
     */
    private function loadATFCSS()
    {
        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $websiteURL = rtrim(get_option('home'), '/') . '/?pagespeedninja=no';

        $data = array(
            'url' => $websiteURL,
            'apikey' => $this->options['apikey'],
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
    private function update_config()
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $network_wide = is_plugin_active_for_network("{$this->plugin_slug}/{$this->plugin_name}.php");
        $this->activate($network_wide);
        $this->options = get_option('pagespeedninja_config');
    }

    /** @return void */
    private function set_locale()
    {
        require_once $this->plugin_dir_path . 'includes/class-pagespeedninja-i18n.php';
        $plugin_i18n = new PagespeedNinja_i18n();
        $plugin_i18n->set_domain($this->get_plugin_slug());
        add_action('plugins_loaded', array($plugin_i18n, 'load_plugin_textdomain'));
    }

    /** @return void */
    private function define_config_hooks()
    {
        require_once $this->plugin_dir_path . 'admin/class-pagespeedninja-admin-config.php';
        $plugin_admin_config = new PagespeedNinja_AdminConfig();

        add_filter('pre_update_option_pagespeedninja_config', array($plugin_admin_config, 'validate_config'), 10, 2);
        add_action('update_option_pagespeedninja_config', array($plugin_admin_config, 'update_config'), 10, 2);
        add_action('update_site_option_pagespeedninja_config', array($plugin_admin_config, 'update_config'), 10, 2);
    }

    /** @return void */
    private function define_cache_hooks()
    {
        require_once $this->plugin_dir_path . 'includes/class-pagespeedninja-cache-hooks.php';
        $plugin_cache_hooks = new PagespeedNinja_Cache_Hooks($this->get_plugin_slug(), $this->get_plugin_name(), $this->get_version());
        $plugin_cache_hooks->define_cache_hooks();
    }

    /** @return void */
    private function define_admin_hooks()
    {
        require_once $this->plugin_dir_path . 'admin/class-pagespeedninja-admin.php';
        $plugin_admin = new PagespeedNinja_Admin($this->get_plugin_slug(), $this->get_plugin_name(), $this->get_version());

        add_action('admin_init', array($plugin_admin, 'admin_init'));
        add_action('admin_menu', array($plugin_admin, 'admin_menu'));
        add_action('network_admin_menu', array($plugin_admin, 'network_admin_menu'));
        add_action('admin_head', array($plugin_admin, 'admin_head'));
        add_filter('plugin_action_links_' . plugin_basename($this->plugin_dir_path . 'pagespeedninja.php'),
            array($plugin_admin, 'admin_plugin_settings_link'));
        add_filter('network_admin_plugin_action_links_' . plugin_basename($this->plugin_dir_path . 'pagespeedninja.php'),
            array($plugin_admin, 'network_admin_plugin_settings_link'));
        add_filter('plugin_row_meta', array($plugin_admin, 'admin_plugin_meta_links'), 10, 2);


        require_once $this->plugin_dir_path . 'admin/class-pagespeedninja-admin-ajax.php';
        $plugin_admin_ajax = new PagespeedNinja_AdminAjax($plugin_admin);

        add_action('wp_ajax_pagespeedninja_get_cache_size', array($plugin_admin_ajax, 'get_cache_size'));

        add_action('wp_ajax_pagespeedninja_clear_images', array($plugin_admin_ajax, 'clear_images'));
        add_action('wp_ajax_pagespeedninja_clear_image_errors', array($plugin_admin_ajax, 'clear_image_errors'));
        add_action('wp_ajax_pagespeedninja_clear_loaded', array($plugin_admin_ajax, 'clear_loaded'));
        add_action('wp_ajax_pagespeedninja_clear_cache_expired', array($plugin_admin_ajax, 'clear_cache_expired'));
        add_action('wp_ajax_pagespeedninja_clear_cache_all', array($plugin_admin_ajax, 'clear_cache_all'));
        add_action('wp_ajax_pagespeedninja_clear_pagecache_expired', array($plugin_admin_ajax, 'clear_pagecache_expired'));
        add_action('wp_ajax_pagespeedninja_clear_pagecache_all', array($plugin_admin_ajax, 'clear_pagecache_all'));
        add_action('wp_ajax_pagespeedninja_update_atfcss', array($plugin_admin_ajax, 'update_atfcss'));
        add_action('wp_ajax_pagespeedninja_save_psi_scores', array($plugin_admin_ajax, 'save_psi_scores'));

        add_action('wp_ajax_pagespeedninja_key', array($plugin_admin_ajax, 'ajax_key'));

        add_action('wp_ajax_pagespeedninja_dismiss_licensekey_notice', array($plugin_admin_ajax, 'dismiss_licensekey_notice'));
    }

    /** @return void */
    private function define_public_hooks()
    {
        require_once $this->plugin_dir_path . 'public/class-pagespeedninja-public.php';
        $plugin_public = new PagespeedNinja_Public($this->get_plugin_name(), $this->get_version());

        add_action('plugins_loaded', array($plugin_public, 'plugins_loaded'), 0);
        // Smart Slider 3: priority=-100
        // Better AMP: priority=2 (redirect to AMP)
        add_action('template_redirect', array($plugin_public, 'template_redirect'), -150);

        add_filter('wp_cache_meta', array($plugin_public, 'wp_cache_meta'));
        add_action('wp_footer', array($plugin_public, 'wp_footer'), 1000000);
    }
}
