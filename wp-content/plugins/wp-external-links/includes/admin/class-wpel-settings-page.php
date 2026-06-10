<?php

/**
 * Class WPEL_Settings_Page
 *
 * @package  WPEL
 * @category WordPress Plugin
 * @version  2.3
 * @link     https://www.webfactoryltd.com/
 * @license  Dual licensed under the MIT and GPLv2+ licenses
 */
final class WPEL_Settings_Page extends WPRun_Base_1x0x0
{

    /**
     * @var string
     */
    private $menu_slug = 'wpel-settings-page';

    /**
     * @var string
     */
    private $current_tab = null;

    /**
     * @var array
     */
    private $tabs = array();

    /**
     * @var WPEL_Network_Page
     */
    private $network_page = null;

    /**
     * Initialize
     */
    protected function init($network_page, array $fields_objects)
    {
        $this->network_page = $network_page;

        $this->tabs = array(
            'external-links' => array(
                'title'     => __('External Links', 'wp-external-links'),
                'icon'      => false,
                'fields'    => $fields_objects['external-links'],
            ),
            'internal-links' => array(
                'title'     => __('Internal Links', 'wp-external-links'),
                'icon'      => false,
                'fields'    => $fields_objects['internal-links'],
            ),
            'excluded-links' => array(
                'title'     => __('Excluded Links', 'wp-external-links'),
                'icon'      => false,
                'fields'    => $fields_objects['excluded-links'],
            ),
            'exceptions' => array(
                'title'     => __('Exceptions', 'wp-external-links'),
                'icon'      => false,
                'fields'    => $fields_objects['exceptions'],
            ),
            'link-rules' => array(
              'title'     => '<span class="dashicons dashicons-star-filled"></span>' . __('Link Rules', 'wp-external-links'),
              'icon'      => false,
          ),

          'exit-confirmation' => array(
            'title'     => '<span class="dashicons dashicons-star-filled"></span>' . __('Exit Confirmation', 'wp-external-links'),
            'icon'      => false,
            'fields'    => $fields_objects['exit-confirmation'],
        ),
            'link-checking' => array(
                'title'     => '<span class="dashicons dashicons-star-filled"></span>' . __('Link Checker', 'wp-external-links'),
                'icon'      => false,
          ),
            'pro' => array(
                'title'     => __('PRO', 'wp-external-links'),
                'icon'      => false,
          ),
            'support' => array(
                'title'     => __('Support', 'wp-external-links'),
                'icon'      => false,
            ),
        );

        // check excluded links tab available
        if ($this->get_option_value('excludes_as_internal_links', 'exceptions')) {
            unset($this->tabs['excluded-links']);
        }

        // get current tab
        //phpcs:ignore because nonce is not needed as this just sets the current tab and can be linked directly
        if(isset($_GET['tab'])){ //phpcs:ignore
            $this->current_tab = sanitize_text_field($_GET['tab']); //phpcs:ignore
        }

        // set default tab
        if ( !isset($this->current_tab) || !key_exists($this->current_tab, $this->tabs)) {
            reset($this->tabs);
            $this->current_tab = key($this->tabs);
        }

        add_filter('plugin_action_links_' . plugin_basename(TEST_WPEL_PLUGIN_FILE), array($this, 'plugin_action_links'));
        add_filter('admin_footer_text', array($this, 'admin_footer_text'));
        add_action('admin_action_wpel_install_wpcaptcha', array($this, 'install_wpcaptcha'));
    }

    // auto download / install / activate WP Captcha plugin
    function install_wpcaptcha()
    {
        check_ajax_referer('install_wpcaptcha');

        if (false === current_user_can('manage_options')) {
            wp_die('Sorry, you have to be an admin to run this action.');
        }

        $plugin_slug = 'advanced-google-recaptcha/advanced-google-recaptcha.php';
        $plugin_zip = 'https://downloads.wordpress.org/plugin/advanced-google-recaptcha.latest-stable.zip';

        @include_once ABSPATH . 'wp-admin/includes/plugin.php';
        @include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        @include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        @include_once ABSPATH . 'wp-admin/includes/file.php';
        @include_once ABSPATH . 'wp-admin/includes/misc.php';
        echo '<style>
		body{
			font-family: sans-serif;
			font-size: 14px;
			line-height: 1.5;
			color: #444;
		}
		</style>';

        echo '<div style="margin: 20px; color:#444;">';
        echo 'If things are not done in a minute <a target="_parent" href="' . esc_url(admin_url('plugin-install.php?s=google%20recaptcha%20webfactory&tab=search&type=term')) . '">install the plugin manually via Plugins page</a><br><br>';
        echo 'Starting ...<br><br>';

        wp_cache_flush();
        $upgrader = new Plugin_Upgrader();
        echo 'Check if Advanced Google ReCaptcha is already installed ... <br />';
        if (self::is_plugin_installed($plugin_slug)) {
            echo 'Advanced Google ReCaptcha is already installed! <br /><br />Making sure it\'s the latest version.<br />';
            $upgrader->upgrade($plugin_slug);
            $installed = true;
        } else {
            echo 'Installing Advanced Google ReCaptcha.<br />';
            $installed = $upgrader->install($plugin_zip);
        }
        wp_cache_flush();

        if (!is_wp_error($installed) && $installed) {
            echo 'Activating Advanced Google ReCaptcha.<br />';
            $activate = activate_plugin($plugin_slug);

            if (is_null($activate)) {
                echo 'Advanced Google ReCaptcha Activated.<br />';

                echo '<script>setTimeout(function() { top.location = "admin.php?page=wpel-settings-page"; }, 1000);</script>';
                echo '<br>If you are not redirected in a few seconds - <a href="admin.php?page=wpel-settings-page" target="_parent">click here</a>.';
            }
        } else {
            echo 'Could not install Advanced Google ReCaptcha. You\'ll have to <a target="_parent" href="' . esc_url(admin_url('plugin-install.php?s=google%20recaptcha%20webfactory&tab=search&type=term')) . '">download and install manually</a>.';
        }

        echo '</div>';
    } // install_wpcaptcha

    static function is_plugin_installed($slug)
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();

        if (!empty($all_plugins[$slug])) {
            return true;
        } else {
            return false;
        }
    } // is_plugin_installed

    /**
     * Add powered by text in admin footer
     *
     * @param string  $text  Default footer text.
     *
     * @return string
     */
    function admin_footer_text($text)
    {
        $current_screen = get_current_screen();
        if (!empty($current_screen) && $current_screen->id == 'toplevel_page_wpel-settings-page') {
            $plugin_version = get_option('wpel-version');
            $text = '<i>WP External Links v' . esc_attr($plugin_version) . ' by <a href="https://www.webfactoryltd.com?ref=wp-external-links" title="WebFactory Ltd" target="_blank">WebFactory Ltd</a>. Please <a target="_blank" href="https://wordpress.org/support/plugin/wp-external-links/reviews/#new-post" title="Rate the plugin">rate the plugin <span>★★★★★</span></a> to help us spread the word. Thank you 🙌</i>';
        }

        return $text;
    } // admin_footer_text



    /**
     * Add "Configure Settings" action link to plugins table, left part
     *
     * @param array  $links  Initial list of links.
     *
     * @return array
     */
    function plugin_action_links($links)
    {
        $settings_link = '<a href="' . admin_url('admin.php?page=wpel-settings-page') . '" title="Open WP External Links Settings">Configure</a>';
        $pro_link = '<a href="' . admin_url('admin.php?page=wpel-settings-page#open-pro-dialog') . '" title="Get PRO version"><b>Get PRO</b></a>';

        array_unshift($links, $pro_link);
        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Get option value
     * @param string $key
     * @param string $type
     * @return string
     * @triggers E_USER_NOTICE Option value cannot be found
     */
    public function get_option_value($key, $type = null)
    {
        if ('own_admin_menu' == $key) {
          return '1';
        }

        if (null === $type) {
            foreach ($this->tabs as $tab_key => $values) {
                if (!isset($values['fields'])) {
                    continue;
                }

                $option_values = $values['fields']->get_option_values();

                if (!isset($option_values[$key])) {
                    continue;
                }

                return $option_values[$key];
            }
        } else if (isset($this->tabs[$type]['fields'])) {
            $option_values = $this->tabs[$type]['fields']->get_option_values();
            return @$option_values[$key];
        }

        if($key == 'icon_type'){
            return false;
        }
    }

    /**
     * Action for "admin_menu"
     */
    protected function action_admin_menu()
    {
        $capability = $this->network_page->get_option_value('capability');

        $own_admin_menu = $this->get_option_value('own_admin_menu', 'admin');

        if ('1' === $own_admin_menu) {
            $this->page_hook = add_menu_page(
                __('WP External Links', 'wp-external-links')          // page title
                ,
                __('WP External Links', 'wp-external-links')           // menu title
                ,
                $capability                               // capability
                ,
                $this->menu_slug                          // id
                ,
                $this->get_callback('show_admin_page')  // callback
                ,
                WPEL_PLUGIN_URL . 'public/images/icon-small.png'  // icon
                ,
                null                                      // position
            );
        } else {
            $this->page_hook = add_options_page(
                __('WP External Links', 'wp-external-links')          // page title
                ,
                __('WP External Links', 'wp-external-links')           // menu title
                ,
                $capability                               // capability
                ,
                $this->menu_slug                          // id
                ,
                $this->get_callback('show_admin_page')  // callback
            );
        }

        add_action('load-' . $this->page_hook, $this->get_callback('add_help_tabs'));
    }

    /**
     * Set default option values for new created sites
     * @param integer $blog_id
     */
    protected function action_wpmu_new_blog($blog_id)
    {
        $default_site_id = $this->network_page->get_option_value('default_settings_site');

        foreach ($this->tabs as $tab_key => $values) {
            if (!isset($values['fields'])) {
                continue;
            }

            $option_name = $values['fields']->get_setting('option_name');

            $default_option_values = get_blog_option($default_site_id, $option_name, array());
            update_blog_option($blog_id, $option_name, $default_option_values);
        }
    }

    /**
     * Action for "admin_enqueue_scripts"
     */
    protected function action_admin_enqueue_scripts()
    {
        $current_screen = get_current_screen();
        $plugin_version = get_option('wpel-version');

        if ($current_screen->id == 'toplevel_page_wpel-settings-page' || $current_screen->id == 'settings_page_wpel-settings-page') {
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-accordion');

            wp_enqueue_style('wp-jquery-ui-dialog');
            wp_enqueue_script('jquery-ui-position');
            wp_enqueue_script('jquery-ui-dialog');

            wp_enqueue_style('wpel-font-awesome');
            wp_enqueue_style('wpel-admin-style');
            wp_enqueue_script('wpel-admin-script');

            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');

            wp_enqueue_style('jquery-ui-smoothness', plugins_url('/public/css/jquery-ui.css', WPEL_Plugin::get_plugin_file()), false, $plugin_version);
            wp_enqueue_style('wpel-admin-font', plugins_url('/public/css/poppins.css', WPEL_Plugin::get_plugin_file()), false, $plugin_version);
        }

        wp_enqueue_style('wpel-admin-global-style');
    }

    /**
     * Show Admin Page
     */
    protected function show_admin_page()
    {
        $template_file = WPEL_Plugin::get_plugin_dir('/templates/settings-page/main.php');
        $page = $this->get_option_value('own_admin_menu') ? 'admin.php' : 'options-general.php';
        $page_url = admin_url() . $page . '?page=' . $this->menu_slug;

        $template_vars = array(
            'tabs'              => $this->tabs,
            'current_tab'       => $this->current_tab,
            'page_url'          => $page_url,
            'menu_slug'         => $this->menu_slug,
            'own_admin_menu'    => $this->get_option_value('own_admin_menu', 'admin'),
        );

        $this->show_template($template_file, $template_vars);
    }

    /**
     * Add help tabs
     */
    protected function add_help_tabs()
    {
        $screen = get_current_screen();
        return;

        $screen->add_help_tab(array(
            'id'        => 'under-construction',
            'title'     => __('Under Construction', 'wp-external-links'),
            'callback'  => $this->get_callback('show_help_tab'),
        ));
        $screen->add_help_tab(array(
            'id'        => 'data-attributes',
            'title'     => __('Data Attributes', 'wp-external-links'),
            'callback'  => $this->get_callback('show_help_tab'),
        ));
    }

    /**
     * @param WP_Screen $screen
     * @param array     $args
     */
    protected function show_help_tab($screen, array $args)
    {
        $template_file = WPEL_Plugin::get_plugin_dir('/templates/settings-page/help-tabs/' . $args['id'] . '.php');
        $this->show_template($template_file);
    }
}
