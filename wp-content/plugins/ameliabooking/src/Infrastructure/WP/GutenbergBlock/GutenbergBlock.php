<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\GutenbergBlock;

use AmeliaBooking\Application\Services\Bookable\BookableApplicationService;
use AmeliaBooking\Application\Services\Bookable\AbstractPackageApplicationService;
use AmeliaBooking\Application\Services\Booking\EventApplicationService;
use AmeliaBooking\Application\Services\Location\AbstractLocationApplicationService;
use AmeliaBooking\Application\Services\User\ProviderApplicationService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Entity\Bookable\Service\Service;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Domain\Factory\Bookable\Service\ServiceFactory;
use AmeliaBooking\Domain\Services\Booking\EventDomainService;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\CategoryRepository;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\ServiceRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\EventTagsRepository;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;
use AmeliaBooking\Infrastructure\WP\Integrations\IvyForms\IvyFormsService;
use AmeliaBooking\Infrastructure\WP\Integrations\PluginInstaller;
use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\WP\SettingsService\SettingsStorage;
use AmeliaBooking\Infrastructure\WP\ShortcodeService\ShortcodeAliasService;
use Exception;

/**
 * Class GutenbergBlock
 *
 * @package AmeliaBooking\Infrastructure\WP\GutenbergBlock
 */
class GutenbergBlock
{
    /** @var array */
    private static $entities;

    public static function init()
    {
        $class = get_called_class();

        // Register block type for frontend rendering (dynamic blocks with save: null)
        if (function_exists('register_block_type')) {
            add_action('init', function () use ($class) {
                call_user_func([$class, 'registerBlockForRendering']);
            });
        }

        // Editor-only: enqueue scripts, styles, and localize data
        if (is_admin() && function_exists('register_block_type')) {
            if (
                substr($_SERVER['PHP_SELF'], '-8') == 'post.php' ||
                substr($_SERVER['PHP_SELF'], '-12') == 'post-new.php'
            ) {
                if (self::isGutenbergActive()) {
                    add_action(
                        'enqueue_block_editor_assets',
                        function () use ($class) {
                            call_user_func([$class, 'registerBlockType']);
                        }
                    );
                }
            }
        }
    }

    /**
     * Enqueue shared Amelia block icon script
     */
    public static function enqueueSharedIcon()
    {
        static $enqueued = false;

        if (!$enqueued) {
            wp_enqueue_script(
                'amelia_block_icon',
                AMELIA_URL . 'public/js/gutenberg/amelia-block-icon.js',
                array('wp-element'),
                AMELIA_VERSION
            );

            $settingsService = new SettingsService(new SettingsStorage());
            $localize = 'window.wpAmeliaUseNeutralShortcodes=' . wp_json_encode(ShortcodeAliasService::shouldUseNeutralShortcodes($settingsService)) . ';'
                . 'window.wpAmeliaPluginName=' . wp_json_encode(ShortcodeAliasService::getWhiteLabelPluginName($settingsService)) . ';'
                . 'window.wpAmeliaBuilderBrandName=' . wp_json_encode(ShortcodeAliasService::getBuilderBrandName($settingsService)) . ';'
                . 'window.wpAmeliaShortcodeAliases=' . wp_json_encode(ShortcodeAliasService::getAliases()) . ';'
                . 'window.wpAmeliaPluginImage=' . wp_json_encode(ShortcodeAliasService::getWhiteLabelPluginImage($settingsService)) . ';';
            wp_add_inline_script('amelia_block_icon', $localize, 'before');

            $enqueued = true;
        }
    }

    /**
     * Enqueue shared Amelia placeholder styles for block editor
     */
    public static function enqueueSharedStyles()
    {
        static $enqueued = false;

        if (!$enqueued) {
            wp_enqueue_style(
                'amelia_gutenberg_placeholder_styles',
                AMELIA_URL . 'public/js/gutenberg/amelia-gutenberg-placeholder.css',
                [],
                AMELIA_VERSION
            );
            $enqueued = true;
        }
    }

    /**
     * Localize desktop/mobile preview image URLs for a Gutenberg block script.
     *
     * @param string $scriptHandle
     * @param string $objectName
     * @param string $desktopFile
     * @param string $mobileFile
     */
    public static function localizePreviewImages($scriptHandle, $objectName, $desktopFile, $mobileFile)
    {
        wp_localize_script(
            $scriptHandle,
            $objectName,
            [
                'desktop' => AMELIA_URL . 'public/img/shortcode/' . $desktopFile,
                'mobile'  => AMELIA_URL . 'public/img/shortcode/' . $mobileFile,
            ]
        );
    }

    /**
     * Backend strings for the block editor, with white-label placeholders already resolved.
     *
     * @return array
     */
    public static function getBlockStrings(): array
    {
        static $strings = null;

        if ($strings === null) {
            $settingsService = new SettingsService(new SettingsStorage());

            $strings = self::applyBrandName(
                BackendStrings::getAllStrings(),
                ShortcodeAliasService::getBuilderBrandName($settingsService),
                (bool)ShortcodeAliasService::shouldUseNeutralShortcodes($settingsService)
            );
        }

        return $strings;
    }

    /**
     * @param array  $strings
     * @param string $brandName
     * @param bool   $neutralShortcodes
     *
     * @return array
     */
    private static function applyBrandName(array $strings, string $brandName, bool $neutralShortcodes): array
    {
        foreach ($strings as $key => $value) {
            if (is_array($value)) {
                $strings[$key] = self::applyBrandName($value, $brandName, $neutralShortcodes);

                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            $value = str_replace('{pluginName}', $brandName, $value);

            $strings[$key] = $neutralShortcodes ? preg_replace('/\s\(Legacy\)$/', '', $value) : $value;
        }

        return $strings;
    }

    /**
     * Register block type with attributes and render_callback for frontend rendering.
     * Override in child classes.
     */
    public static function registerBlockForRendering()
    {
    }

    /**
     * Register block for gutenberg
     */
    public static function registerBlockType()
    {
    }

    /**
     * @param string $shortCode
     * @param array  $allowedTags
     *
     * @return bool
     */
    protected static function isAllowedShortCode(string $shortCode, array $allowedTags): bool
    {
        foreach ($allowedTags as $tag) {
            if (preg_match('/^\[' . preg_quote($tag, '/') . '(?=[\s\]\/])/u', $shortCode) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $view
     * @param string $legacyTag
     *
     * @return array
     */
    protected static function getAllowedShortcodeTags(string $view, string $legacyTag): array
    {
        $aliasTag = ShortcodeAliasService::shortcodeTag($view, $legacyTag);

        return $aliasTag === $legacyTag ? [$legacyTag] : [$legacyTag, $aliasTag];
    }

    /**
     * Check if Block Editor is active.
     *
     * @return bool
     */
    public static function isGutenbergActive()
    {
        // Gutenberg plugin is installed and activated.
        $gutenberg = !(false === has_filter('replace_editor', 'gutenberg_init'));

        // Block editor since 5.0.
        $block_editor = version_compare($GLOBALS['wp_version'], '5.0-beta', '>');

        if (!$gutenberg && !$block_editor) {
            return false;
        }

        if (self::isClassicEditorPluginActive()) {
            $editor_option       = get_option('classic-editor-replace');
            $block_editor_active = array('no-replace', 'block');

            return in_array($editor_option, $block_editor_active, true);
        }

        // Fix for conflict with Avada - Fusion builder and gutenberg blocks
        if (class_exists('FusionBuilder') && !(isset($_GET['gutenberg-editor']))) {
            return false;
        }

        // Fix for conflict with Disable Gutenberg plugin
        if (class_exists('DisableGutenberg')) {
            return false;
        }

        // Fix for conflict with WP Bakery Page Builder
        if (class_exists('Vc_Manager') && (isset($_GET['classic-editor']))) {
            return false;
        }

        // Fix for conflict with WooCommerce product page
        if (isset($_GET['post_type']) && $_GET['post_type'] === 'product' && class_exists('WooCommerce')) {
            return false;
        }

        return true;
    }

    /**
     * Check if Classic Editor plugin is active
     *
     * @return bool
     */
    public static function isClassicEditorPluginActive()
    {

        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (is_plugin_active('classic-editor/classic-editor.php')) {
            return true;
        }

        return false;
    }

    /**
     * Get entities data for front-end
     */
    public static function getEntitiesData()
    {
        return (new self())->getAllEntitiesForGutenbergBlocks();
    }

    /**
     * Get Entities for Gutenberg blocks
     */
    public function getAllEntitiesForGutenbergBlocks()
    {
        if (!empty(self::$entities)) {
            return self::$entities;
        }

        try {
            /** @var Container $container */
            $container = require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php';

            /** @var AbstractLocationApplicationService $locationAS */
            $locationAS = $container->get('application.location.service');

            $locations = $locationAS->getAllOrderedByName();

            $resultData['locations'] = $locations->toArray();

            /** @var ServiceRepository $serviceRepository */
            $serviceRepository = $container->get('domain.bookable.service.repository');
            /** @var CategoryRepository $categoryRepository */
            $categoryRepository = $container->get('domain.bookable.category.repository');
            /** @var BookableApplicationService $bookableAS */
            $bookableAS = $container->get('application.bookable.service');

            $services = $serviceRepository->getAllArrayIndexedById();

            $categories = $categoryRepository->getAllIndexedById();

            $bookableAS->addServicesToCategories($categories, $services);

            $resultData['categories'] = $categories->toArray();

            /** @var ProviderRepository $providerRepository */
            $providerRepository = $container->get('domain.users.providers.repository');

            /** @var ProviderApplicationService $providerAS */
            $providerAS = $container->get('application.user.provider.service');

            /** @var Collection $providers */
            $providers = $providerRepository->getByFieldValue('type', 'provider');

            $providerServicesData = $providerRepository->getProvidersServices();

            foreach ((array)$providerServicesData as $providerKey => $providerServices) {
                /** @var Provider|null $provider */
                $provider = $providers->getItem($providerKey);

                $providerServiceList = new Collection();

                foreach ((array)$providerServices as $serviceKey => $providerService) {
                    /** @var Service|null $service */
                    $service = $services->getItem($serviceKey);

                    if ($service && $provider) {
                        $providerServiceList->addItem(
                            ServiceFactory::create(array_merge($service->toArray(), $providerService)),
                            $service->getId()->getValue()
                        );
                    }
                }

                $provider->setServiceList($providerServiceList);
            }

            /** @var Provider $currentUser */
            $currentUser = $container->get('logged.in.user');

            $resultData['employees'] = $providerAS->removeAllExceptUser(
                $providers->toArray(),
                $currentUser
            );

            $finalData = self::getOnlyCatSerLocEmp($resultData);

            /** @var EventApplicationService $eventAS */
            $eventAS = $container->get('application.booking.event.service');

            /** @var Collection $events */
            $events = $eventAS->getEventsByCriteria(
                [
                    'dates' => [DateTimeService::getNowDateTime()],
                ],
                [
                    'fetchEventsPeriods' => true,
                ],
                100
            );

            $finalData['events'] = $events->toArray();

            /** @var EventDomainService $eventDS */
            $eventDS = $container->get('domain.booking.event.service');

            /** @var SettingsService $settingsDS */
            $settingsDS = $container->get('domain.settings.service');

            $finalData['events'] = $eventDS->getShortcodeForEventList($container, $finalData['events']);

            $tags = new Collection();

            if ($settingsDS->isFeatureEnabled('eventTags')) {
                /** @var EventTagsRepository $eventTagsRepository */
                $eventTagsRepository = $container->get('domain.booking.event.tag.repository');

                /** @var Collection $tags * */
                $tags = $eventTagsRepository->getAllDistinctByCriteria(
                    [
                        'eventIds' => array_column($finalData['events'], 'id')
                    ]
                );
            }

            /** @var AbstractPackageApplicationService $packageApplicationService */
            $packageApplicationService = $container->get('application.bookable.package');

            $finalData['packages'] = $packageApplicationService->getPackagesArray();

            $finalData['tags'] = $tags->toArray();

            $forms = $settingsDS->isFeatureEnabled('ivy') && PluginInstaller::isPluginActive('ivyforms')
                ? IvyFormsService::getForms()
                : [];

            $finalData['ivy'] = $forms
                ? array_merge([['value' => '0', 'label' => BackendStrings::get('ivy_select')]], $forms)
                : [];

            self::$entities = ['data' => $finalData];

            return self::$entities;
        } catch (Exception $exception) {
            return ['data' => [
                'categories'   => [],
                'servicesList' => [],
                'locations'    => [],
                'employees'    => [],
                'events'       => [],
                'tags'         => [],
                'packages'     => [],
                'ivy'          => [],
            ]];
        }
    }

    /**
     * Get only Categories, Services, Employees and Locations for Gutenberg blocks
     */
    public static function getOnlyCatSerLocEmp($resultData)
    {
        $data = [];
        $data['categories']   = [];
        $data['servicesList'] = [];
        if ($resultData['categories'] !== []) {
            for ($i = 0; $i < count($resultData['categories']); $i++) {
                $data['categories'][] = [
                    'id'   => $resultData['categories'][$i]['id'],
                    'name' => $resultData['categories'][$i]['name']
                ];
                if ($resultData['categories'][$i]['serviceList'] !== []) {
                    for ($j = 0; $j < count($resultData['categories'][$i]['serviceList']); $j++) {
                        if (!$resultData['categories'][$i]['serviceList'][$j]['show']) {
                            continue;
                        }

                        $data['servicesList'][] = [
                            'id'   => $resultData['categories'][$i]['serviceList'][$j]['id'],
                            'name' => $resultData['categories'][$i]['serviceList'][$j]['name']
                        ];
                    }
                }
            }
        } else {
            $data['categories']   = [];
            $data['servicesList'] = [];
        }

        if ($resultData['locations'] !== []) {
            for ($i = 0; $i < count($resultData['locations']); $i++) {
                $data['locations'][] = [
                    'id'   => $resultData['locations'][$i]['id'],
                    'name' => $resultData['locations'][$i]['name']
                ];
            }
        } else {
            $data['locations'] = [];
        }

        if ($resultData['employees'] !== []) {
            for ($i = 0; $i < count($resultData['employees']); $i++) {
                if (!$resultData['employees'][$i]['show']) {
                    continue;
                }

                $data['employees'][] = [
                    'id'        => $resultData['employees'][$i]['id'],
                    'firstName' => $resultData['employees'][$i]['firstName'],
                    'lastName'  => $resultData['employees'][$i]['lastName'],
                ];
            }
        } else {
            $data['employees'] = [];
        }

        return $data;
    }
}
