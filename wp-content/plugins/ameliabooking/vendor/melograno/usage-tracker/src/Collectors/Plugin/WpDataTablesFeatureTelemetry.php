<?php

declare(strict_types=1);

namespace AmeliaVendor\Melograno\UsageTracker\Collectors\Plugin;

/**
 * wpDataTables feature telemetry: skins + table options (enabled ⇒ in use).
 */
final class WpDataTablesFeatureTelemetry
{
    /** @var list<string> */
    private const FEATURE_CODES = [
        'sorting',
        'filtering',
        'fixedHeader',
        'fixedColumns',
    ];

    /** @var list<string> */
    private const ALLOWED_SKINS = [
        'material',
        'light',
        'graphite',
        'aqua',
        'purple',
        'dark',
        'raspberry-cream',
        'mojito',
        'dark-mojito',
    ];

    /**
     * @return array{
     *     skins: array<string, int>,
     *     features: array<string, bool>,
     *     feature_metrics: array<string, array{usage_count: int}>
     * }|null
     */
    public static function collect(): ?array
    {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'get_results')) {
            return null;
        }

        $output = defined('ARRAY_A') ? ARRAY_A : 'ARRAY_A';
        $rows = $wpdb->get_results(
            'SELECT sorting, filtering, advanced_settings FROM ' . $wpdb->prefix . 'wpdatatables',
            $output
        );

        if ($rows === null) {
            if (!empty($wpdb->last_error)) {
                error_log('[melograno/usage-tracker] wpDataTables feature telemetry failed: ' . $wpdb->last_error);
            }

            return null;
        }

        $usageCounts = array_fill_keys(self::FEATURE_CODES, 0);
        $skins = [];
        $defaultSkin = self::resolveDefaultSkin();

        foreach ($rows as $row) {
            $advanced = self::decodeAdvancedSettings($row['advanced_settings'] ?? null);

            $skin = self::resolveSkin($advanced, $defaultSkin);
            $skins[$skin] = ($skins[$skin] ?? 0) + 1;

            $sorting = (int) ($row['sorting'] ?? 0);
            $filtering = (int) ($row['filtering'] ?? 0);

            if ($sorting === 1) {
                $usageCounts['sorting']++;
            }
            if ($filtering === 1) {
                $usageCounts['filtering']++;
            }
            if (!empty($advanced['fixed_header'])) {
                $usageCounts['fixedHeader']++;
            }
            if (!empty($advanced['fixed_columns'])) {
                $usageCounts['fixedColumns']++;
            }
        }

        return self::buildResult($skins, $usageCounts);
    }

    /**
     * @param array<string, int> $skins
     * @param array<string, int> $usageCounts
     * @return array{
     *     skins: array<string, int>,
     *     features: array<string, bool>,
     *     feature_metrics: array<string, array{usage_count: int}>
     * }
     */
    private static function buildResult(array $skins, array $usageCounts): array
    {
        $features = [];
        $featureMetrics = [];

        foreach (self::FEATURE_CODES as $code) {
            $count = (int) ($usageCounts[$code] ?? 0);
            $featureMetrics[$code] = ['usage_count' => $count];
            if ($count > 0) {
                $features[$code] = true;
            }
        }

        return [
            'skins' => $skins,
            'features' => $features,
            'feature_metrics' => $featureMetrics,
        ];
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private static function decodeAdvancedSettings($raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $advanced
     */
    private static function resolveSkin(array $advanced, string $defaultSkin): string
    {
        $skin = isset($advanced['tableSkin']) ? trim((string) $advanced['tableSkin']) : '';
        if ($skin !== '' && in_array($skin, self::ALLOWED_SKINS, true)) {
            return $skin;
        }

        return $defaultSkin;
    }

    private static function resolveDefaultSkin(): string
    {
        $fallback = 'light';
        if (!function_exists('get_option')) {
            return $fallback;
        }

        $option = get_option('wdtBaseSkin', $fallback);
        $skin = is_string($option) ? trim($option) : $fallback;

        return in_array($skin, self::ALLOWED_SKINS, true) ? $skin : $fallback;
    }
}
