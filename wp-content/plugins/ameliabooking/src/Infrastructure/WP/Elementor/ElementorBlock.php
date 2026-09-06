<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\Elementor;

use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\WP\SettingsService\SettingsStorage;
use AmeliaBooking\Infrastructure\WP\ShortcodeService\ShortcodeAliasService;
use Elementor\AmeliaBookingElementorWidget;
use Elementor\AmeliaStepBookingElementorWidget;
use Elementor\AmeliaStepBookingButtonElementorWidget;
use Elementor\AmeliaCatalogBookingElementorWidget;
use Elementor\AmeliaCustomerPanelElementorWidget;
use Elementor\AmeliaCatalogElementorWidget;
use Elementor\AmeliaEmployeePanelElementorWidget;
use Elementor\AmeliaEventsElementorWidget;
use Elementor\AmeliaEventsListBookingElementorWidget;
use Elementor\AmeliaEventsListBookingButtonElementorWidget;
use Elementor\AmeliaEventsCalendarBookingElementorWidget;
use Elementor\AmeliaSearchElementorWidget;
use Elementor\Plugin;

/**
 * Class ElementorBlock
 *
 * @package AmeliaBooking\Infrastructure\WP\Elementor
 */
class ElementorBlock
{
    protected static $instance;

    public static function get_instance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    protected function __construct()
    {
        add_action('elementor/editor/before_enqueue_scripts', [$this, 'widget_styles']);
        add_action('elementor/editor/after_enqueue_styles', [$this, 'widget_styles']);
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        add_action('elementor/frontend/after_enqueue_styles', [$this, 'widget_styles']);
        add_action('elementor/elements/categories_registered', [$this, 'register_widget_categories']);
        add_filter('admin_body_class', [$this, 'addWhiteLabelBodyClass']);
    }

    public function includes()
    {
        require_once(AMELIA_PATH . '/src/Infrastructure/WP/Elementor/AmeliaElementorWhiteLabelHelper.php');
        require_once(AMELIA_PATH . '/src/Infrastructure/WP/Elementor/ElementorSharedShortcodeWidget.php');
        require_once(AMELIA_PATH . '/src/Infrastructure/WP/Elementor/AmeliaStepBookingElementorWidget.php');
        require_once(AMELIA_PATH . '/src/Infrastructure/WP/Elementor/AmeliaStepBookingButtonElementorWidget.php');
        require_once(AMELIA_PATH . '/src/Infrastructure/WP/Elementor/AmeliaCatalogBookingElementorWidget.php');
        require_once(AMELIA_PATH . '/src/Infrastructure/WP/Elementor/AmeliaBookingElementorWidget.php');
        require_once(AMELIA_PATH . '/src/Infrastructure/WP/Elementor/AmeliaSearchElementorWidget.php');
        require_once(AMELIA_PATH . '/src/Infrastructure/WP/Elementor/AmeliaCatalogElementorWidget.php');
        require_once(AMELIA_PATH . '/src/Infrastructure/WP/Elementor/AmeliaEventsElementorWidget.php');
        require_once(AMELIA_PATH . '/src/Infrastructure/WP/Elementor/AmeliaEventsListBookingElementorWidget.php');
        require_once(AMELIA_PATH . '/src/Infrastructure/WP/Elementor/AmeliaEventsListBookingButtonElementorWidget.php');
        require_once(AMELIA_PATH . '/src/Infrastructure/WP/Elementor/AmeliaEventsCalendarBookingElementorWidget.php');
        require_once(AMELIA_PATH . '/src/Infrastructure/WP/Elementor/AmeliaCustomerPanelElementorWidget.php');
        require_once(AMELIA_PATH . '/src/Infrastructure/WP/Elementor/AmeliaEmployeePanelElementorWidget.php');
    }

    public function register_widgets()
    {
        $this->includes();
        Plugin::instance()->widgets_manager->register(new AmeliaStepBookingElementorWidget());
        Plugin::instance()->widgets_manager->register(new AmeliaStepBookingButtonElementorWidget());
        Plugin::instance()->widgets_manager->register(new AmeliaCatalogBookingElementorWidget());
        Plugin::instance()->widgets_manager->register(new AmeliaBookingElementorWidget());
        Plugin::instance()->widgets_manager->register(new AmeliaSearchElementorWidget());
        Plugin::instance()->widgets_manager->register(new AmeliaCatalogElementorWidget());
        Plugin::instance()->widgets_manager->register(new AmeliaEventsElementorWidget());
        Plugin::instance()->widgets_manager->register(new AmeliaEventsListBookingElementorWidget());
        Plugin::instance()->widgets_manager->register(new AmeliaEventsListBookingButtonElementorWidget());
        Plugin::instance()->widgets_manager->register(new AmeliaEventsCalendarBookingElementorWidget());
        Plugin::instance()->widgets_manager->register(new AmeliaCustomerPanelElementorWidget());
        Plugin::instance()->widgets_manager->register(new AmeliaEmployeePanelElementorWidget());
    }

    public function widget_styles()
    {
        wp_register_style('amelia-elementor-widget-font', AMELIA_URL . 'public/css/frontend/elementor.css', array(), AMELIA_VERSION);
        wp_enqueue_style('amelia-elementor-widget-font');

        $settingsService = new SettingsService(new SettingsStorage());
        $useNeutral = ShortcodeAliasService::shouldUseNeutralShortcodes($settingsService);

        // Elementor editor does not use admin_body_class; inject the flag on html/body instead.
        if ($useNeutral) {
            wp_register_script('amelia-elementor-white-label', false, [], AMELIA_VERSION, false);
            wp_enqueue_script('amelia-elementor-white-label');
            wp_add_inline_script(
                'amelia-elementor-white-label',
                '(function(){var c="amelia-white-label-active";document.documentElement.classList.add(c);'
                . 'function a(){if(document.body){document.body.classList.add(c)}}'
                . 'document.body?a():document.addEventListener("DOMContentLoaded",a);})();'
            );

            // Custom image uploaded: replace Amelia logo on Elementor widget icons.
            // White-label without image: keep Amelia logo from elementor.css (same as Divi).
            $pluginImage = ShortcodeAliasService::getWhiteLabelPluginImage($settingsService);
            if ($pluginImage !== '') {
                wp_register_style('amelia-white-label-builders', false, ['amelia-elementor-widget-font'], AMELIA_VERSION);
                wp_enqueue_style('amelia-white-label-builders');

                $iconSelectors = [
                    '.elementor-element .icon .amelia-logo:before',
                    '.elementor-element .icon i.amelia-logo:before',
                    '.elementor-element .icon .amelia-logo-beta:before',
                    '.elementor-element .icon i.amelia-logo-beta:before',
                    '.elementor-element .icon .amelia-logo-outdated:before',
                    '.elementor-element .icon i.amelia-logo-outdated:before',
                    '.amelia-elementor-content::before',
                    '.amelia-elementor-content-beta::before',
                    '.amelia-elementor-content-outdated::before',
                ];
                $selectors = [];
                foreach ($iconSelectors as $iconSelector) {
                    $selectors[] = 'html.amelia-white-label-active ' . $iconSelector;
                    $selectors[] = 'body.amelia-white-label-active ' . $iconSelector;
                }
                $css = implode(', ', $selectors) . '{'
                    . 'content:""!important;'
                    . 'display:block!important;'
                    . 'background-image:url(' . esc_url_raw($pluginImage) . ')!important;'
                    . 'background-repeat:no-repeat!important;'
                    . 'background-position:center center!important;'
                    . 'background-size:contain!important;'
                    . '}';
                wp_add_inline_style('amelia-white-label-builders', $css);
            }
        }
    }

    /**
     * @param string $classes
     *
     * @return string
     */
    public function addWhiteLabelBodyClass($classes)
    {
        if (ShortcodeAliasService::shouldUseNeutralShortcodes()) {
            $classes .= ' amelia-white-label-active';
        }

        return $classes;
    }

    public function register_widget_categories($elements_manager)
    {
        require_once(AMELIA_PATH . '/src/Infrastructure/WP/Elementor/AmeliaElementorWhiteLabelHelper.php');

        $elements_manager->add_category(
            'amelia-elementor',
            [
                'title' => \Elementor\AmeliaElementorWhiteLabelHelper::categoryTitle(),
                'icon' => \Elementor\AmeliaElementorWhiteLabelHelper::icon(),
            ],
            1
        );
    }
}
