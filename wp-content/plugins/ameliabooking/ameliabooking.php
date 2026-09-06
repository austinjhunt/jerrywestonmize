<?php
/*
Plugin Name: Amelia
Plugin URI: https://wpamelia.com/
Description: Amelia is a simple yet powerful automated booking specialist, working 24/7 to make sure your customers can make appointments and events even while you sleep!
Version: 9.8.1
Author: Melograno Ventures
Author URI: https://melograno.io/
Text Domain: wpamelia
Domain Path: /languages
*/

namespace AmeliaBooking;

use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Licence\LicenceConstants;
use AmeliaBooking\Application\Services\Payment\PaymentApplicationService;
use AmeliaBooking\Infrastructure\Routes\Routes;
use AmeliaBooking\Infrastructure\Services\Payment\SquareService;
use AmeliaBooking\Infrastructure\WP\ButtonService\ButtonService;
use AmeliaBooking\Infrastructure\WP\config\Menu;
use AmeliaBooking\Infrastructure\WP\Elementor\ElementorBlock;
use AmeliaBooking\Infrastructure\WP\ErrorService\ErrorService;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaBookingGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaStepBookingGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaStepBookingButtonGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaCatalogBookingGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaCatalogGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaCustomerCabinetGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaEmployeeCabinetGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaEventsGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaEventsListBookingButtonGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaEventsListBookingGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaEventsCalendarBookingGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaSearchGutenbergBlock;
use AmeliaBooking\Infrastructure\WP\Integrations\WooCommerce\WooCommerceService;
use AmeliaBooking\Infrastructure\WP\SettingsService\SettingsStorage;
use AmeliaBooking\Infrastructure\WP\ShortcodeService\ShortcodeAliasService;
use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;
use AmeliaBooking\Infrastructure\WP\UserRoles\SuperAdminRoleService;
use AmeliaBooking\Infrastructure\WP\UserRoles\UserRoles;
use AmeliaBooking\Infrastructure\WP\WPMenu\Submenu;
use AmeliaBooking\Infrastructure\WP\WPMenu\SubmenuPageHandler;
use AmeliaBooking\Infrastructure\WP\Compatibility\LiteSpeedCacheCompatibility;
use AmeliaBooking\Infrastructure\WP\CustomPostTypes\CustomPostTypesBootstrap;
use AmeliaBooking\Infrastructure\WP\WPMenu\AdminBarMenu;
use AmeliaBooking\Infrastructure\Common\AmeliaErrorHandler;
use Exception;
use Slim\App;
use Slim\Psr7\Factory\ResponseFactory;
use AmeliaBooking\Infrastructure\Licence;
use AmeliaVendor\Melograno\UsageTracker\Collectors\Plugin\AmeliaCollector;
use AmeliaVendor\Melograno\UsageTracker\Core\UsageTracker;
use WP\MCP\Core\McpAdapter;

// No direct access
defined('ABSPATH') or die('No script kiddies please!');

if (!defined('AMELIA_DOMAIN')) {
    define('AMELIA_DOMAIN', 'wpamelia');
}

// Const for path root
if (!defined('AMELIA_PATH')) {
    define('AMELIA_PATH', __DIR__);
}

// Const for uploads path
if (!defined('AMELIA_UPLOADS_PATH')) {
    $uploadDir = wp_upload_dir();
    define('AMELIA_UPLOADS_PATH', !empty($uploadDir['basedir']) ? $uploadDir['basedir'] : '');
}

// Const for uploads url
if (!defined('AMELIA_UPLOADS_URL')) {
    $uploadUrl = wp_upload_dir();
    define('AMELIA_UPLOADS_URL', !empty($uploadUrl['baseurl']) ? set_url_scheme($uploadUrl['baseurl']) : '');
}

// Const for uploads url
if (!defined('AMELIA_UPLOADS_FILES_URL')) {
    define('AMELIA_UPLOADS_FILES_URL', AMELIA_UPLOADS_URL . '/amelia/files/');
}

// Const for uploads files path
if (!defined('AMELIA_UPLOADS_FILES_PATH')) {
    define('AMELIA_UPLOADS_FILES_PATH', AMELIA_UPLOADS_PATH . '/amelia/files/');
}

// Const for uploads files path
if (!defined('AMELIA_UPLOADS_FILES_PATH_USE')) {
    define('AMELIA_UPLOADS_FILES_PATH_USE', true);
}

// Const for URL root
if (!defined('AMELIA_URL')) {
    define('AMELIA_URL', plugin_dir_url(__FILE__));
}

if (!defined('AMELIA_HOME_URL')) {
    define('AMELIA_HOME_URL', get_home_url());
}

// Const for URL Actions identifier
if (!defined('AMELIA_ACTION_SLUG')) {
    define('AMELIA_ACTION_SLUG', 'action=wpamelia_api&call=');
}

// Const for URL Actions identifier
if (!defined('AMELIA_ACTION_URL')) {
    define('AMELIA_ACTION_URL', admin_url('admin-ajax.php', '') . '?' . AMELIA_ACTION_SLUG);
}

// Const for URL Actions identifier
if (!defined('AMELIA_PAGE_URL')) {
    define('AMELIA_PAGE_URL', get_site_url() . '/wp-admin/admin.php?page=');
}

// Const for URL Actions identifier
if (!defined('AMELIA_LOGIN_URL')) {
    define('AMELIA_LOGIN_URL', get_site_url() . '/wp-login.php?redirect_to=');
}

// Const for Amelia version
if (!defined('AMELIA_VERSION')) {
    define('AMELIA_VERSION', '9.8.1');
}

// Const for site URL
if (!defined('AMELIA_SITE_URL')) {
    define('AMELIA_SITE_URL', get_site_url());
}

// Const for plugin basename
if (!defined('AMELIA_PLUGIN_BASENAME')) {
    define('AMELIA_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

// Const for plugin slug (used in store API and plugins_api filter)
if (!defined('AMELIA_PLUGIN_SLUG')) {
    define('AMELIA_PLUGIN_SLUG', 'ameliabooking');
}

// Const for Amelia SMS API
if (!defined('AMELIA_SMS_API_URL')) {
    define('AMELIA_SMS_API_URL', 'https://smsapi.wpamelia.com/');
    define('AMELIA_SMS_VENDOR_ID', 36082);
    define('AMELIA_SMS_IS_SANDBOX', false);
    define('AMELIA_SMS_PRODUCT_ID_10', 595657);
    define('AMELIA_SMS_PRODUCT_ID_20', 595658);
    define('AMELIA_SMS_PRODUCT_ID_50', 595659);
    define('AMELIA_SMS_PRODUCT_ID_100', 595660);
    define('AMELIA_SMS_PRODUCT_ID_200', 595661);
    define('AMELIA_SMS_PRODUCT_ID_500', 595662);
}

if (!defined('AMELIA_STORE_API_URL')) {
    define('AMELIA_STORE_API_URL', 'https://store.melograno.io/api/');
}

if (!defined('MELOGRANO_BI_GATE_URL')) {
    define('MELOGRANO_BI_GATE_URL', 'https://bi.melograno.io');
}

if (!defined('AMELIA_DEV')) {
    define('AMELIA_DEV', false);
}

if (!defined('AMELIA_NGROK_URL')) {
    define('AMELIA_NGROK_URL', 'nonmelodiously-barnlike-anika.ngrok-free.dev');
}

if (!defined('AMELIA_MIDDLEWARE_URL')) {
    define('AMELIA_MIDDLEWARE_URL', 'https://middleware.wpamelia.com/');
}

if (!defined('AMELIA_MAILCHIMP_CLIENT_ID')) {
    define('AMELIA_MAILCHIMP_CLIENT_ID', '459163389015');
}

$ameliaVendorAutoload = AMELIA_PATH . '/vendor/autoload.php';

if (!is_readable($ameliaVendorAutoload)) {
    if (!defined('AMELIA_VENDOR_AUTOLOAD_MISSING')) {
        define('AMELIA_VENDOR_AUTOLOAD_MISSING', true);
    }

    add_action(
        'admin_notices',
        static function () {
            if (!current_user_can('activate_plugins')) {
                return;
            }

            printf(
                '<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
                esc_html__('Amelia:', AMELIA_DOMAIN),
                esc_html__(
                    'Composer dependencies are missing (vendor/autoload.php). From the plugin directory run: composer install',
                    AMELIA_DOMAIN
                )
            );
        }
    );

    return;
}

require_once $ameliaVendorAutoload;

/**
 * @noinspection AutoloadingIssuesInspection
 *
 * Class Plugin
 *
 * @package AmeliaBooking
 *
 * @phpcs:ignoreFile
 * @SuppressWarnings(PHPMD)
 */
class Plugin
{
    const DEFAULT_PLUGIN_NAME = 'Amelia';
    const DEFAULT_PLUGIN_URI = 'https://wpamelia.com/';
    const DEFAULT_PLUGIN_DESCRIPTION = 'Amelia is a simple yet powerful automated booking specialist, working 24/7 to make sure your customers can make appointments and events even while you sleep!';
    const DEFAULT_AUTHOR = 'Melograno Ventures';
    const DEFAULT_AUTHOR_URI = 'https://melograno.io/';

    private const LEGACY_SHORTCODE_HANDLERS = [
        'ameliabooking'               => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\StepBookingShortcodeService',
        'ameliasearch'                => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\CatalogBookingShortcodeService',
        'ameliacatalog'               => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\CatalogBookingShortcodeService',
        'ameliaevents'                => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsShortcodeService',
        'ameliaeventslistbooking'     => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsListBookingShortcodeService',
        'ameliaeventscalendarbooking' => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsCalendarBookingShortcodeService',
        'ameliacustomerpanel'         => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\CabinetCustomerShortcodeService',
        'ameliaemployeepanel'         => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\CabinetEmployeeShortcodeService',
        'ameliastepbooking'           => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\StepBookingShortcodeService',
        'ameliacatalogbooking'        => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\CatalogBookingShortcodeService',
    ];

    private const ALIAS_SHORTCODE_HANDLERS = [
        'booking'               => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\StepBookingShortcodeService',
        'search'                => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\CatalogBookingShortcodeService',
        'catalog'               => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\CatalogBookingShortcodeService',
        'events'                => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsShortcodeService',
        'stepbooking'           => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\StepBookingShortcodeService',
        'catalogbooking'        => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\CatalogBookingShortcodeService',
        'eventslistbooking'     => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsListBookingShortcodeService',
        'eventscalendarbooking' => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsCalendarBookingShortcodeService',
        'customer_panel'        => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\CabinetCustomerShortcodeService',
        'employee_panel'        => 'AmeliaBooking\Infrastructure\WP\ShortcodeService\CabinetEmployeeShortcodeService',
    ];

    /**
     * API Call
     *
     * @throws \InvalidArgumentException
     */
    public static function wpAmeliaApiCall()
    {
        try {
            /** @var Container $container */
            $container = require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php';

            $responseFactory = new ResponseFactory();

            $app = new App($responseFactory, $container);

            // Initialize all API routes
            Routes::routes($app, $container);

            $app->addBodyParsingMiddleware();
            $app->addRoutingMiddleware();

            $errorMiddleware = $app->addErrorMiddleware(true, true, true);

            $errorMiddleware->setDefaultErrorHandler(
                new AmeliaErrorHandler(
                    $app->getCallableResolver(),
                    $app->getResponseFactory()
                )
            );

            $request = $container->get('request');

            $app->run($request);

            exit();
        } catch (Exception $e) {
            echo 'ERROR: ' . esc_html($e->getMessage());
        }
    }

    static function square_weekly_token_refresh($schedules) {
        $schedules['weekly'] = array(
            'interval' => 604800,
            'display' => __('Add weekly cron to refresh square access token every 7 days', AMELIA_DOMAIN)
        );
        return $schedules;
    }

    static function razorpay_reconciliation_schedule($schedules) {
        $schedules['amelia_fifteen_minutes'] = array(
            'interval' => 900,
            'display' => __('Every 15 minutes (Amelia Razorpay payment reconciliation)', AMELIA_DOMAIN)
        );
        return $schedules;
    }

    /**
     * Initialize the plugin
     */
    public static function init()
    {
        $settingsService = new SettingsService(new SettingsStorage());
        $savedVersion = $settingsService->getSetting('activation', 'version');

        UsageTracker::init(new AmeliaCollector(), __FILE__, $savedVersion, AMELIA_VERSION);

        // Initialize LiteSpeed Cache compatibility
        LiteSpeedCacheCompatibility::init();

        self::weglotConflict($settingsService, true);

        load_plugin_textdomain(AMELIA_DOMAIN, false, plugin_basename(__DIR__) . '/languages/' . AMELIA_LOCALE . '/');

        self::weglotConflict($settingsService, false);

        if (WooCommerceService::isEnabled()) {
            if (!empty($settingsService->getCategorySettings('payments')['wc']['dashboard'])) {
                add_filter('woocommerce_prevent_admin_access', '__return_false');
            }

            if (!empty($settingsService->getCategorySettings('payments')['wc']['enabled'])) {
                try {
                    WooCommerceService::init($settingsService);
                } catch (Exception $e) {
                }
            } else {
                WooCommerceService::setContainer(require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php');
                WooCommerceService::$settingsService = $settingsService;

                add_filter('woocommerce_after_order_itemmeta', [WooCommerceService::class, 'orderItemMeta'], 10, 3);
            }
        }

        if (!empty($settingsService->getCategorySettings('payments')['square']['enabled']) &&
            !empty($settingsService->getCategorySettings('payments')['square']['accessToken'])) {
            add_filter( 'cron_schedules', [self::class, 'square_weekly_token_refresh'] );

            if ( ! wp_next_scheduled( 'amelia_square_access_token_refresh' ) ) {
                wp_schedule_event( time(), 'weekly', 'amelia_square_access_token_refresh' );
            }

            /** @var Container $container */
            $container = require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php';

            /** @var SquareService $squareService */
            $squareService = $container->get('infrastructure.payment.square.service');

            add_action( 'amelia_square_access_token_refresh', [$squareService, 'refreshAccessToken'] );
        }

        if (!empty($settingsService->getCategorySettings('payments')['razorpay']['enabled'])) {
            add_filter( 'cron_schedules', [self::class, 'razorpay_reconciliation_schedule'] );

            if (!wp_next_scheduled('amelia_razorpay_reconcile_pending_payments')) {
                wp_schedule_event( time(), 'amelia_fifteen_minutes', 'amelia_razorpay_reconcile_pending_payments' );
            }

            /** @var Container $container */
            $container = require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php';

            /** @var PaymentApplicationService $paymentApplicationService */
            $paymentApplicationService = $container->get('application.payment.service');

            add_action(
                'amelia_razorpay_reconcile_pending_payments',
                [$paymentApplicationService, 'reconcilePendingRazorpayPayments']
            );
        }

        if (!wp_next_scheduled('amelia_log_retention_cleanup')) {
            wp_schedule_event(time(), 'daily', 'amelia_log_retention_cleanup');
        }

        add_action('amelia_log_retention_cleanup', static function () {
            /** @var Container $container */
            $container = require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php';
            $container->get('infrastructure.logger.retention')->cleanup();
        });

        $ameliaRole = UserRoles::getUserAmeliaRole(wp_get_current_user());

        // Register Gutenberg blocks for rendering on frontend (works for all users, logged in or not)
        AmeliaStepBookingGutenbergBlock::init();
        AmeliaStepBookingButtonGutenbergBlock::init();
        AmeliaCatalogBookingGutenbergBlock::init();
        AmeliaBookingGutenbergBlock::init();
        AmeliaSearchGutenbergBlock::init();
        AmeliaCatalogGutenbergBlock::init();
        AmeliaEventsGutenbergBlock::init();
        AmeliaEventsListBookingButtonGutenbergBlock::init();
        AmeliaEventsListBookingGutenbergBlock::init();
        AmeliaEventsCalendarBookingGutenbergBlock::init();
        AmeliaCustomerCabinetGutenbergBlock::init();
        AmeliaEmployeeCabinetGutenbergBlock::init();

        // Init menu if user is logged in with amelia role
        if (in_array($ameliaRole, ['admin', 'manager', 'provider', 'customer'])) {
            if ($ameliaRole === 'admin') {
                ErrorService::setNotices();
            }

            // Add TinyMCE button for shortcode generator
            ButtonService::renderButton();

            add_filter('block_categories_all', array('AmeliaBooking\Plugin', 'addAmeliaBlockCategory'), 10, 2);
            add_filter('learn-press/frontend-default-scripts', array('AmeliaBooking\Plugin', 'learnPressConflict'));
        }

        if (!is_admin()) {
            add_filter('learn-press/frontend-default-scripts', array('AmeliaBooking\Plugin', 'learnPressConflict'));
            self::registerShortcodes();
        }

        if (defined('ELEMENTOR_VERSION')) {
            ElementorBlock::get_instance();
        }

        $theme = wp_get_theme();

        $theme = $theme->parent() ?: $theme;

        if ($theme && strtolower($theme->get('Name')) === 'divi' || strtolower($theme->get_template()) === 'divi') {
            $version = $theme->get('Version');

            if (version_compare($version, '5.0', '<')) {
                // Only enqueue jQuery early in Divi builder to avoid frontend conflicts
                add_action('wp_head', function() {
                    if (function_exists('et_fb_is_enabled') && et_fb_is_enabled()) {
                        wp_enqueue_script('jquery');
                        wp_print_scripts('jquery');
                    }
                }, 0);
                require_once AMELIA_PATH . '/extensions/divi_amelia/divi_amelia.php';
            } else {
                require_once AMELIA_PATH . '/extensions/divi_5_amelia/divi-5-amelia.php';
            }
        }

        // Load BuddyBoss integration only if feature is enabled
        if ($settingsService->isFeatureEnabled('buddyboss')) {
            require_once AMELIA_PATH . '/extensions/buddyboss-platform-addon/buddyboss-platform-addon.php';
        }

        CustomPostTypesBootstrap::init();
    }

    /**
     * Creating Amelia block category in Gutenberg
     */
    public static function addAmeliaBlockCategory($categories, $post)
    {
        return array_merge(
            array(
                array(
                    'slug'  => 'amelia-blocks',
                    'title' => self::getShortcodeBuilderBrandName(),
                ),
            ),
            $categories
        );
    }

    /**
     * Fix for conflict with Weglot plugin
     * @param $settingsService
     * @param $init
     */
    public static function weglotConflict($settingsService, $init)
    {
        if (defined('AMELIA_LOCALE_FORCED') &&
            AMELIA_LOCALE_FORCED &&
            function_exists('weglot_get_current_language')
        ) {
            try {
                if ($init && !defined('AMELIA_LOCALE')) {
                    $weglotCurrentLanguage = weglot_get_current_language();

                    $ameliaUsedLanguages = array_flip($settingsService->getSetting('general', 'usedLanguages'));

                    require_once ABSPATH . 'wp-admin/includes/translation-install.php';

                    global $locale;

                    $potentialLanguages = [];

                    foreach (wp_get_available_translations() as $key => $value) {
                        if (substr($key, 0, 2) === substr($weglotCurrentLanguage, 0, 2)) {
                            $potentialLanguages[] = $key;
                        }
                    }

                    foreach ($potentialLanguages as $potentialLanguage) {
                        if (array_key_exists($potentialLanguage, $ameliaUsedLanguages)) {
                            $locale = $potentialLanguage;
                            break;
                        }
                    }
                } else {
                    global $locale;

                    $locale = AMELIA_LOCALE_FORCED;
                }
            } catch (\Exception $e) {

            }
        }
    }

    /**
     * Fix for conflict with LearnPress plugin
     */
    public static function learnPressConflict($data)
    {
        $post = get_post(get_the_ID());

        if ($post && self::contentHasBookingShortcode($post->post_content)) {
            return array();
        }

        return $data;
    }

    /**
     * @return void
     */
    private static function registerShortcodes()
    {
        foreach (self::LEGACY_SHORTCODE_HANDLERS as $tag => $handlerClass) {
            add_shortcode($tag, [$handlerClass, 'shortcodeHandler']);
        }

        foreach (ShortcodeAliasService::getAliases() as $view => $tag) {
            if (!isset(self::ALIAS_SHORTCODE_HANDLERS[$view])) {
                continue;
            }

            $handlerClass = self::ALIAS_SHORTCODE_HANDLERS[$view];
            add_shortcode($tag, [$handlerClass, 'shortcodeHandler']);
        }
    }

    /**
     * @param string $content
     *
     * @return bool
     */
    private static function contentHasBookingShortcode($content)
    {
        $shortcodes = array_merge(
            array_keys(self::LEGACY_SHORTCODE_HANDLERS),
            array_values(ShortcodeAliasService::getAliases()),
            ['ameliacabinet']
        );

        foreach ($shortcodes as $tag) {
            if (has_shortcode($content, $tag)) {
                return true;
            }
        }

        return false;
    }

    public static function initMenu()
    {
        $settingsService = new SettingsService(new SettingsStorage());

        $menuItems = new Menu($settingsService);

        // Init admin menu
        $wpMenu = new Submenu(
            new SubmenuPageHandler($settingsService),
            $menuItems()
        );

        $wpMenu->addOptionsPages();
    }

    public static function initAdminBar()
    {
        $settingsService = new SettingsService(new SettingsStorage());

        add_action('admin_bar_menu', function ($wpAdminBar) use ($settingsService) {
            $adminBarMenu = new AdminBarMenu($settingsService);
            $adminBarMenu->addAdminBarMenu($wpAdminBar);
        }, 100);
    }

    public static function adminInit()
    {
        $settingsService = new SettingsService(new SettingsStorage());

        self::handleWelcomePageRedirect($settingsService);

        $savedVersion = $settingsService->getSetting('activation', 'version');

        if (AMELIA_VERSION !== $savedVersion) {
            $settingsService->setSetting('activation', 'version', AMELIA_VERSION);

            require_once ABSPATH . 'wp-admin/includes/plugin.php';

            deactivate_plugins(AMELIA_PLUGIN_BASENAME);
            activate_plugin(AMELIA_PLUGIN_BASENAME);
        }
    }

    /**
     * Handle welcome page redirect and access control
     *
     * @param SettingsService $settingsService
     */
    public static function handleWelcomePageRedirect($settingsService)
    {
        $currentPage = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        $showWelcomePage = $settingsService->getSetting('activation', 'showWelcomePage');
        $isNewInstallation = $settingsService->getSetting('activation', 'isNewInstallation');

        if (get_transient('amelia_activation_redirect') && $currentPage !== 'wpamelia-welcome') {
            delete_transient('amelia_activation_redirect');

            if ($showWelcomePage && $isNewInstallation) {
                wp_safe_redirect(admin_url('admin.php?page=wpamelia-welcome'));

                exit;
            }
        }

        if (!$showWelcomePage && $currentPage === 'wpamelia-welcome') {
            wp_safe_redirect(admin_url('admin.php?page=wpamelia-dashboard'));

            exit;
        }
    }

    /**
     * @param $networkWide
     */
    public static function activation($networkWide)
    {
        load_plugin_textdomain(AMELIA_DOMAIN, false, plugin_basename(__DIR__) . '/languages/' . get_locale() . '/');

        // Check PHP version
        if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50500) {
            deactivate_plugins(AMELIA_PLUGIN_BASENAME);
            wp_die(
                BackendStrings::get('php_version_message'),
                BackendStrings::get('php_version_title'),
                array('response' => 200, 'back_link' => TRUE)
            );
        }
        //Network activation
        if ($networkWide && function_exists('is_multisite') && is_multisite()) {
            Infrastructure\WP\InstallActions\ActivationMultisite::init();
        }

        Infrastructure\WP\InstallActions\ActivationDatabaseHook::init();

        CustomPostTypesBootstrap::onActivation();

        set_transient('amelia_activation_redirect', true, 30);
    }

    /**
     * Plugin deactivation hook.
     */
    public static function deactivation()
    {
        // Once the plugin is inactive its code no longer runs, so the all_plugins filter cannot
        // rebrand the row — the header on disk is the only remaining source of the plugin name.
        self::syncPluginHeader();
    }

    /**
     * @param $dirPath
     */
    public static function deleteFolderContent($dirPath)
    {
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }

        $files = glob($dirPath . '*', GLOB_MARK);

        foreach ($files as $file) {
            if (is_dir($file)) {
                self::deleteFolderContent($file);
            } else {
                unlink($file);
            }
        }
    }

    /**
     * @throws Domain\Common\Exceptions\InvalidArgumentException
     */
    public static function deletion()
    {
        $settingsService = new SettingsService(new SettingsStorage());

        if ($settingsService->getSetting('activation', 'deleteTables')) {
            //Network deletion
            if (function_exists('is_multisite') &&
                is_multisite()
            ) {
                Infrastructure\WP\InstallActions\DeletionMultisite::delete();
            }

            Infrastructure\WP\InstallActions\DeleteDatabaseHook::delete();


            // Delete Roles
            global $wp_roles;

            $wp_roles->remove_role('wpamelia-customer');
            $wp_roles->remove_role('wpamelia-provider');
            $wp_roles->remove_role('wpamelia-manager');


            // Delete Settings
            delete_option('amelia_settings');
            delete_option('amelia_stash');
            delete_option('amelia_show_wpdt_promo');
            UsageTracker::deleteStoredOptions();


            // Delete Files
            foreach (['/amelia/css', '/amelia/files/tmp', '/amelia/files', '/amelia'] as $path) {
                if (is_dir(AMELIA_UPLOADS_PATH . $path)) {
                    self::deleteFolderContent(AMELIA_UPLOADS_PATH . $path);
                    rmdir(AMELIA_UPLOADS_PATH . $path);
                }
            }
        }
    }

    /**
     * Show WPDT promo notice
     **/
    public static function wpdt_dashboard_promo()
    {
        $wpAmeliaPage = isset($_GET['page']) ? $_GET['page'] : '';

        require_once AMELIA_PATH . '/extensions/wpdt/functions.php';

        if( is_admin() && (strpos($wpAmeliaPage,'wpamelia-dashboard') !== false) &&
            amelia_installed_plugins_wpdt_promotion() &&
            get_option( 'amelia_show_wpdt_promo' ) == 'yes'
        ) {
            include AMELIA_PATH . '/extensions/wpdt/promote_wpdt.php';
            wp_enqueue_style('wdt-promo-css', AMELIA_URL . 'public/css/backend/promote_wpdt.css');
        }
    }

    /**
     * Remove WPDT promo notice
     **/
    public static function amelia_remove_wpdt_promo_notice()
    {
        update_option( 'amelia_show_wpdt_promo', 'no' );
        echo json_encode( array("success") );
        exit;
    }

    /**
     * Hide admin notices on Amelia pages
     **/
    public static function hide_notices_on_amelia_pages()
    {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, AMELIA_DOMAIN)) {
            remove_action('admin_notices', 'update_nag', 3);
            remove_action('network_admin_notices', 'update_nag', 3);
            remove_action('admin_notices', 'maintenance_nag');
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
        }

        add_action('admin_notices', array('AmeliaBooking\Plugin', 'wpdt_dashboard_promo'));
    }

    /**
     * @param array $links
     *
     * @return array
     */
    public static function addPluginActionLinks($links)
    {
        $primaryLinks = [
            '<a href="' . admin_url('admin.php?page=wpamelia-dashboard') . '">View</a>',
            '<a href="' . admin_url('admin.php?page=wpamelia-settings') . '">Settings</a>'
        ];

        if (Licence\Licence::getLicence() === LicenceConstants::LITE) {
            $deactivate = [];
            if (isset($links['deactivate'])) {
                $deactivate = ['deactivate' => $links['deactivate']];
                unset($links['deactivate']);
            }

            $upgradeLinks = self::shouldHideExternalLinksOnPluginsPage()
                ? []
                : ['<a href="https://wpamelia.com/pricing/?utm_source=wp_org&utm_medium=wp_org&utm_content=plugin_row&utm_campaign=wp_org" style="color: #5951F6; font-weight: bold;" target="_blank">Get Amelia Pro</a>'];

            return array_merge(
                $primaryLinks,
                $links,
                $upgradeLinks,
                $deactivate
            );
        }

        return array_merge($primaryLinks, $links);
    }

    /**
     * @param array  $links
     * @param string $file
     * @param array  $pluginData
     * @param string $status
     *
     * @return array
     */
    public static function addPluginRowMeta($links, $file, $pluginData, $status)
    {
        if ($file !== AMELIA_PLUGIN_BASENAME) {
            return $links;
        }

        if (self::shouldHideExternalLinksOnPluginsPage()) {
            return array_filter($links, function ($link) {
                return strpos($link, 'melograno.io') === false &&
                    strpos($link, 'wpamelia.com') === false &&
                    strpos($link, 'plugin-install.php?tab=plugin-information') === false &&
                    strpos($link, 'View details') === false &&
                    strpos($link, 'Visit plugin site') === false &&
                    strpos($link, 'Docs') === false;
            });
        }

        $links[] = '<a href="https://wpamelia.com/documentation/" target="_blank" rel="noopener">Docs</a>';

        return $links;
    }

    /**
     * @param array $plugins
     *
     * @return array
     */
    public static function applyPluginsPageWhiteLabel($plugins)
    {
        if (!isset($plugins[AMELIA_PLUGIN_BASENAME])) {
            return $plugins;
        }

        $pluginName = self::getWhiteLabelPluginNameForPluginsPage();

        if (!$pluginName) {
            return self::applyDefaultPluginsPageMetadata($plugins);
        }

        $plugins[AMELIA_PLUGIN_BASENAME]['Name'] = $pluginName;
        $plugins[AMELIA_PLUGIN_BASENAME]['Title'] = $pluginName;
        $plugins[AMELIA_PLUGIN_BASENAME]['Description'] = self::getWhiteLabelPluginDescription($pluginName);

        return $plugins;
    }

    /**
     * @param array $plugins
     *
     * @return array
     */
    private static function applyDefaultPluginsPageMetadata($plugins)
    {
        $plugins[AMELIA_PLUGIN_BASENAME]['Name']        = self::DEFAULT_PLUGIN_NAME;
        $plugins[AMELIA_PLUGIN_BASENAME]['Title']       = self::DEFAULT_PLUGIN_NAME;
        $plugins[AMELIA_PLUGIN_BASENAME]['PluginURI']   = self::DEFAULT_PLUGIN_URI;
        $plugins[AMELIA_PLUGIN_BASENAME]['Description'] = self::DEFAULT_PLUGIN_DESCRIPTION;
        $plugins[AMELIA_PLUGIN_BASENAME]['Author']      = self::DEFAULT_AUTHOR;
        $plugins[AMELIA_PLUGIN_BASENAME]['AuthorName']  = self::DEFAULT_AUTHOR;
        $plugins[AMELIA_PLUGIN_BASENAME]['AuthorURI']   = self::DEFAULT_AUTHOR_URI;

        return $plugins;
    }

    /**
     * Sync plugin header with the current white-label state.
     */
    public static function syncPluginHeader()
    {
        if (!self::isWhiteLabelFeatureEnabledForPluginsPage()) {
            self::restoreDefaultPluginHeader();
            return;
        }

        $pluginName = self::getWhiteLabelPluginNameForPluginsPage();
        $hideExternalLinks = self::shouldHideExternalLinksOnPluginsPage();

        self::writePluginHeader(
            $pluginName ?: self::DEFAULT_PLUGIN_NAME,
            $pluginName ? self::getWhiteLabelPluginDescription($pluginName) : self::DEFAULT_PLUGIN_DESCRIPTION,
            $hideExternalLinks ? '' : self::DEFAULT_PLUGIN_URI,
            $hideExternalLinks ? '' : self::DEFAULT_AUTHOR,
            $hideExternalLinks ? '' : self::DEFAULT_AUTHOR_URI
        );
    }

    /**
     * Restore the checked-in plugin header.
     */
    private static function restoreDefaultPluginHeader()
    {
        self::writePluginHeader(
            self::DEFAULT_PLUGIN_NAME,
            self::DEFAULT_PLUGIN_DESCRIPTION,
            self::DEFAULT_PLUGIN_URI,
            self::DEFAULT_AUTHOR,
            self::DEFAULT_AUTHOR_URI
        );
    }

    /**
     * Sync plugin header after white-label feature or settings changes.
     *
     * @param array $settingsFields
     */
    public static function restoreActivePluginHeader($settingsFields = [])
    {
        if (
            !is_array($settingsFields) ||
            (
                !array_key_exists('whiteLabel', $settingsFields) &&
                !array_key_exists('featuresIntegrations', $settingsFields)
            )
        ) {
            return;
        }

        self::syncPluginHeader();
    }

    /**
     * @param string $name
     * @param string $description
     * @param string $pluginUri
     * @param string $author
     * @param string $authorUri
     */
    private static function writePluginHeader($name, $description, $pluginUri, $author, $authorUri)
    {
        $pluginFile = AMELIA_PATH . '/ameliabooking.php';
        if (!is_readable($pluginFile) || !is_writable($pluginFile)) {
            return;
        }

        $contents = file_get_contents($pluginFile);
        if ($contents === false) {
            return;
        }

        $updated = self::replacePluginHeader($contents, 'Plugin Name', $name, $nameCount);
        $updated = self::replacePluginHeader($updated, 'Plugin URI', $pluginUri, $pluginUriCount);
        $updated = self::replacePluginHeader($updated, 'Description', $description, $descriptionCount);
        $updated = self::replacePluginHeader($updated, 'Author', $author, $authorCount);
        $updated = self::replacePluginHeader($updated, 'Author URI', $authorUri, $authorUriCount);

        if (
            $nameCount !== 1 ||
            $pluginUriCount !== 1 ||
            $descriptionCount !== 1 ||
            $authorCount !== 1 ||
            $authorUriCount !== 1 ||
            $updated === $contents
        ) {
            return;
        }

        if (file_put_contents($pluginFile, $updated, LOCK_EX) !== false && function_exists('wp_clean_plugins_cache')) {
            wp_clean_plugins_cache(true);
        }
    }

    /**
     * @param string $contents
     * @param string $header
     * @param string $value
     * @param int    $count
     *
     * @return string
     */
    private static function replacePluginHeader($contents, $header, $value, &$count)
    {
        // Use horizontal whitespace only — \s would match the newline and swallow the next header line
        // when the current value is empty (e.g. "Plugin URI:" / "Author:" from hide-external-links).
        return preg_replace_callback(
            '/^(' . preg_quote($header, '/') . ':)[^\S\r\n]*.*$/m',
            static function ($matches) use ($value) {
                return $matches[1] . ($value !== '' ? ' ' . $value : '');
            },
            $contents,
            1,
            $count
        );
    }

    /**
     * @return bool
     */
    private static function shouldHideExternalLinksOnPluginsPage()
    {
        if (!self::isWhiteLabelFeatureEnabledForPluginsPage()) {
            return false;
        }

        $whiteLabel = self::getSavedSettingsCategory('whiteLabel');

        return !empty($whiteLabel['hideExternalLinks']);
    }

    /**
     * @return string
     */
    private static function getWhiteLabelPluginNameForPluginsPage()
    {
        if (!self::isWhiteLabelFeatureEnabledForPluginsPage()) {
            return '';
        }

        $whiteLabel = self::getSavedSettingsCategory('whiteLabel');

        return !empty($whiteLabel['pluginName']) ? sanitize_text_field(trim($whiteLabel['pluginName'])) : '';
    }

    /**
     * @return string
     */
    private static function getShortcodeBuilderBrandName()
    {
        if (!self::isWhiteLabelFeatureEnabledForPluginsPage()) {
            return 'Amelia';
        }

        $pluginName = self::getWhiteLabelPluginNameForPluginsPage();

        return $pluginName !== '' ? $pluginName : __('Booking', 'wpamelia');
    }

    /**
     * @param string $pluginName
     *
     * @return string
     */
    private static function getWhiteLabelPluginDescription($pluginName)
    {
        return $pluginName
            . ' is a simple yet powerful automated booking specialist, working 24/7 to make sure your customers can '
            . 'make appointments and events even while you sleep!';
    }

    /**
     * @return bool
     */
    private static function isWhiteLabelFeatureEnabledForPluginsPage()
    {
        $featuresIntegrations = self::getSavedSettingsCategory('featuresIntegrations');
        $whiteLabelFeature = isset($featuresIntegrations['whiteLabel']) ? $featuresIntegrations['whiteLabel'] : null;

        if (!self::isFeatureToggleEnabled($whiteLabelFeature)) {
            return false;
        }

        return Licence\Licence::isFeatureEnabledWithLicense(
            'whiteLabel',
            $whiteLabelFeature
        );
    }

    /**
     * @param array|null $feature
     *
     * @return bool
     */
    private static function isFeatureToggleEnabled($feature)
    {
        return is_array($feature) &&
            array_key_exists('enabled', $feature) &&
            in_array($feature['enabled'], [true, 1, '1'], true);
    }

    /**
     * Read persisted settings before licence modifiers are applied.
     *
     * @param string $category
     *
     * @return array
     */
    private static function getSavedSettingsCategory($category)
    {
        $settings = json_decode(get_option('amelia_settings'), true);

        return isset($settings[$category]) && is_array($settings[$category]) ? $settings[$category] : [];
    }

    public static function enqueueAngieMcpServer()
    {
        global $wp_version;
        if (version_compare($wp_version, '6.5', '<')) {
            return;
        }

        $mcpServerPath = AMELIA_PATH . '/redesign/dist/amelia-angie.js';
        if (!file_exists($mcpServerPath)) {
            return;
        }

        wp_enqueue_script_module(
            'amelia-angie-mcp',
            AMELIA_URL . 'redesign/dist/amelia-angie.js',
            array(),
            AMELIA_VERSION
        );
    }

    /**
     * Resolve AutoUpdateHook without triggering autoload when files are partially removed (e.g. during uninstall).
     *
     * @return class-string|null Fully-qualified class name if loadable, otherwise null.
     */
    private static function getAutoUpdateHookClass()
    {
        $class = __NAMESPACE__ . '\Infrastructure\WP\InstallActions\AutoUpdateHook';
        if (class_exists($class, false)) {
            return $class;
        }
        $path = AMELIA_PATH . '/src/Infrastructure/WP/InstallActions/AutoUpdateHook.php';
        if (!is_file($path)) {
            return null;
        }
        require_once $path;
        return class_exists($class, false) ? $class : null;
    }

    /**
     * Update transient filter — must not reference AutoUpdateHook directly in add_filter (invalid callback if class file is missing during delete).
     *
     * @param mixed $transient
     *
     * @return mixed
     */
    public static function filterPreSetSiteTransientUpdatePlugins($transient)
    {
        $class = self::getAutoUpdateHookClass();
        if ($class === null) {
            return $transient;
        }
        return $class::checkUpdate($transient);
    }

    /**
     * plugins_api filter — safe delegate for same reason as {@see filterPreSetSiteTransientUpdatePlugins}.
     *
     * @param false|object|array $response
     * @param string             $action
     * @param object               $args
     *
     * @return mixed
     */
    public static function filterPluginsApi($response, $action, $args)
    {
        $class = self::getAutoUpdateHookClass();
        if ($class === null) {
            return $response;
        }
        return $class::checkInfo($response, $action, $args);
    }

    /**
     * in_plugin_update_message-* action callback.
     */
    public static function actionInPluginUpdateMessage()
    {
        $class = self::getAutoUpdateHookClass();
        if ($class === null) {
            return;
        }
        $class::addMessageOnPluginsPage();
    }

    /**
     * @param bool|\WP_Error $reply
     * @param string         $package
     * @param \WP_Upgrader   $updater
     * @param mixed          $extra
     *
     * @return bool|\WP_Error|string
     */
    public static function filterUpgraderPreDownload($reply, $package, $updater, $extra = null)
    {
        $class = self::getAutoUpdateHookClass();
        if ($class === null) {
            return $reply;
        }
        return $class::addMessageOnUpdate($reply, $package, $updater);
    }
}

add_action('wp_ajax_amelia_remove_wpdt_promo_notice', array('AmeliaBooking\Plugin', 'amelia_remove_wpdt_promo_notice'));

add_action('admin_head', array('AmeliaBooking\Plugin', 'hide_notices_on_amelia_pages'));

/** Redirect For Outlook Calendar */
if (is_admin()) {
    add_action('wp_loaded', array('AmeliaBooking\Infrastructure\Services\Outlook\OutlookCalendarService', 'handleCallback'));
}

/** Isolate API calls */
add_action('wp_ajax_wpamelia_api', array('AmeliaBooking\Plugin', 'wpAmeliaApiCall'));
add_action('wp_ajax_nopriv_wpamelia_api', array('AmeliaBooking\Plugin', 'wpAmeliaApiCall'));

/** Init the plugin */
add_action('plugins_loaded', array('AmeliaBooking\Plugin', 'init'));

add_action('init', array('AmeliaBooking\Infrastructure\WP\WPMenu\AdminBarMenu', 'enqueueScripts'));
add_action('init', array('AmeliaBooking\Plugin', 'initAdminBar'));

add_action('admin_init', array('AmeliaBooking\Plugin', 'adminInit'));
add_action('add_user_role', array(SuperAdminRoleService::class, 'removeSecondaryAmeliaRoles'), 10, 1);
add_action('load-users.php', array(UserRoles::class, 'syncRoleLabels'));
add_action('load-user-new.php', array(UserRoles::class, 'syncRoleLabels'));
add_action('load-user-edit.php', array(UserRoles::class, 'syncRoleLabels'));
add_action('admin_init', array(UserRoles::class, 'syncSuperAdminRoleAvailability'));
add_filter('editable_roles', array(UserRoles::class, 'filterEditableRoles'));

add_action('admin_menu', array('AmeliaBooking\Plugin', 'initMenu'));

/** Activation hooks */
register_activation_hook(__FILE__, array('AmeliaBooking\Plugin', 'activation'));
register_activation_hook(__FILE__, array('AmeliaBooking\Infrastructure\WP\InstallActions\ActivationRolesHook', 'init'));
register_activation_hook(__FILE__, array('AmeliaBooking\Infrastructure\WP\InstallActions\ActivationSettingsHook', 'init'));
register_deactivation_hook(__FILE__, array('AmeliaBooking\Plugin', 'deactivation'));
register_uninstall_hook(__FILE__, array('AmeliaBooking\Plugin', 'deletion'));

/** Activation hook for new site on multisite setup */
add_action('wpmu_new_blog', array('AmeliaBooking\Infrastructure\WP\InstallActions\ActivationNewSiteMultisite', 'init'));

/** Auto-update hooks only apply to paid versions */
if (Licence\Licence::isPremium()) {
    /** Define the API for updating checking (callbacks on Plugin so hooks stay valid if AutoUpdateHook cannot load — e.g. partial delete) */
    add_filter('pre_set_site_transient_update_plugins', array('AmeliaBooking\Plugin', 'filterPreSetSiteTransientUpdatePlugins'), 21, 1);

    /** Define the alternative response for information checking */
    add_filter('plugins_api', array('AmeliaBooking\Plugin', 'filterPluginsApi'), 20, 3);

    /** Add a message for unavailable auto update if plugin is not activated */
    add_action('in_plugin_update_message-' . AMELIA_PLUGIN_SLUG, array('AmeliaBooking\Plugin', 'actionInPluginUpdateMessage'));

    /** Add error message on plugin update if plugin is not activated */
    add_filter('upgrader_pre_download', array('AmeliaBooking\Plugin', 'filterUpgraderPreDownload'), 10, 4);
}

add_filter('script_loader_tag', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\StepBookingShortcodeService', 'prepareScripts') , 10, 3);
add_filter('style_loader_tag', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\StepBookingShortcodeService', 'prepareStyles') , 10, 3);

add_filter('script_loader_tag', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsListBookingShortcodeService', 'prepareScripts') , 10, 3);
add_filter('style_loader_tag', array('AmeliaBooking\Infrastructure\WP\ShortcodeService\EventsListBookingShortcodeService', 'prepareStyles') , 10, 3);

add_action('thrive_automator_init', array('AmeliaBooking\Infrastructure\WP\Integrations\ThriveAutomator\ThriveAutomatorService', 'init'));
add_filter('all_plugins', array('AmeliaBooking\Plugin', 'applyPluginsPageWhiteLabel'));
add_filter('plugin_row_meta', array('AmeliaBooking\Plugin', 'addPluginRowMeta'), 10, 4);
add_filter('plugin_action_links_' . AMELIA_PLUGIN_BASENAME, array('AmeliaBooking\Plugin', 'addPluginActionLinks'));

/** Re-apply branding to the header on disk — plugin updates ship the file with the default header */
add_action('load-plugins.php', array('AmeliaBooking\Plugin', 'syncPluginHeader'));
add_action('amelia_after_settings_updated', array('AmeliaBooking\Plugin', 'restoreActivePluginHeader'));
add_action(
    'amelia_after_settings_updated',
    array(
        'AmeliaBooking\Infrastructure\WP\InstallActions\ActivationSettingsHook',
        'syncSmsAlphaSenderIdOnWhiteLabelChange',
    )
);

add_action( 'wp_logout',  array('AmeliaBooking\Infrastructure\WP\UserService\UserService', 'logoutAmeliaUser'));
add_action( 'profile_update',  array('AmeliaBooking\Infrastructure\WP\UserService\UserService', 'updateAmeliaUser'), 10, 3);
add_action( 'deleted_user', array('AmeliaBooking\Infrastructure\WP\UserService\UserService', 'removeWPUserConnection'), 10, 1);

if (function_exists('is_plugin_active') && is_plugin_active('angie/angie.php')) {
    add_action('admin_enqueue_scripts', array('AmeliaBooking\Plugin', 'enqueueAngieMcpServer'));
}

if (class_exists(McpAdapter::class)) {
    McpAdapter::instance();

    add_action('mcp_adapter_init', array('\AmeliaBooking\Infrastructure\WP\MCP\AmeliaMcpServerRegistrar', 'init'));
    add_action('wp_abilities_api_categories_init', array('\AmeliaBooking\Infrastructure\WP\MCP\AmeliaAbilitiesRegistrar', 'registerCategories'));
    add_action('wp_abilities_api_init', array('\AmeliaBooking\Infrastructure\WP\MCP\AmeliaAbilitiesRegistrar', 'registerAbilities'));
}
