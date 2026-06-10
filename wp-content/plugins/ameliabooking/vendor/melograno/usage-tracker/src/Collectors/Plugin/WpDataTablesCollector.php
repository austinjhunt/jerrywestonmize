<?php

declare(strict_types=1);

namespace Melograno\UsageTracker\Collectors\Plugin;

use Melograno\UsageTracker\Collectors\BaseCollector;

class WpDataTablesCollector extends BaseCollector
{
    public function getPluginSlug(): string
    {
        return 'wpdatatables';
    }

    public function getConsentOptionName(): string
    {
        return 'wpdatatables_usage_tracking_consent';
    }

    /**
     * @return array<string, mixed>
     */
    protected function pluginPayload(): array
    {
        $data = [
            'plugin_version' => defined('WDT_CURRENT_VERSION') ? WDT_CURRENT_VERSION : null,
        ];

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (function_exists('get_plugin_data') && defined('WDT_ROOT_PATH')) {
            $pluginData = get_plugin_data(WDT_ROOT_PATH . 'wpdatatables.php', false, false);
            if (!empty($pluginData['Version'])) {
                $data['plugin_version'] = $pluginData['Version'];
            }
        }

        $data = array_filter($data, static function ($value) {
            return $value !== null;
        });

        return $data;
    }
}
