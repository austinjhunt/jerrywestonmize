<?php

/**
 * Maps Amelia frontend customize (ecf + fonts) to CSS custom properties on the WPA landing shell,
 * matching v3 {@see eventFormCssVars} and {@see applyCustomFontFaceIfSelected}.
 *
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\CustomPostTypes\Events;

use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\WP\SettingsService\SettingsStorage;

/**
 * Class WpaEventsLandingCustomizeCss
 *
 * @package AmeliaBooking\Infrastructure\WP\CustomPostTypes\Events
 */
final class WpaEventsLandingCustomizeCss
{
    /** @var array<string, string> */
    private const DEFAULT_ECF_COLORS = [
        'colorPrimary'         => '#1246D6',
        'colorSuccess'         => '#019719',
        'colorError'           => '#B4190F',
        'colorWarning'         => '#CCA20C',
        'colorInpBgr'          => '#FFFFFF',
        'colorInpBorder'       => '#D1D5D7',
        'colorInpText'         => '#1A2C37',
        'colorInpPlaceHolder'  => '#808A90',
        'colorDropBgr'         => '#FFFFFF',
        'colorDropBorder'      => '#FFFFFF',
        'colorDropText'        => '#0E1920',
        'colorSbBgr'           => '#FFFFFF',
        'colorSbText'          => '#1A2C37',
        'colorMainBgr'         => '#FFFFFF',
        'colorMainHeadingText' => '#33434C',
        'colorMainText'        => '#1A2C37',
        'colorCardBgr'         => '#FFFFFF',
        'colorCardBorder'      => '#D1D5D7',
        'colorCardText'        => '#1A2C37',
        'colorBtnPrim'         => '#265CF2',
        'colorBtnPrimText'     => '#FFFFFF',
        'colorBtnSec'          => '#1A2C37',
        'colorBtnSecText'      => '#FFFFFF',
        'colorBtnWaiting'      => '#CCA20C',
        'colorBtnWaitingText'  => '#FFFFFF',
    ];

    /** @var array<string, string|bool> */
    private const DEFAULT_FONTS = [
        'fontFamily'         => 'Amelia Roboto, sans-serif',
        'fontUrl'            => '',
        'customFontFamily'   => '',
        'customFontSelected' => false,
    ];

    /**
     * Inline CSS appended to amelia_wpa_events_single: @font-face (optional) + .amelia-wpa variables.
     */
    public static function buildInlineCss(): string
    {
        $settingsService = new SettingsService(new SettingsStorage());
        $frontend        = $settingsService->getFrontendSettings();

        $customized = [];
        if (isset($frontend['customizedData']) && is_array($frontend['customizedData'])) {
            $customized = $frontend['customizedData'];
        }

        $ecfColors = self::DEFAULT_ECF_COLORS;
        if (!empty($customized['ecf']['colors']) && is_array($customized['ecf']['colors'])) {
            $ecfColors = array_merge(self::DEFAULT_ECF_COLORS, $customized['ecf']['colors']);
        }

        $fonts = self::DEFAULT_FONTS;
        if (!empty($customized['fonts']) && is_array($customized['fonts'])) {
            $fonts = array_merge(self::DEFAULT_FONTS, $customized['fonts']);
        }

        $fontFaceBlock = self::buildCustomFontFaceBlock($fonts);

        $c = static function (string $key) use ($ecfColors): string {
            $val = isset($ecfColors[$key]) && is_string($ecfColors[$key]) ? $ecfColors[$key] : '';

            return self::escapeCssColorValue($val);
        };

        $fontFamily = isset($fonts['fontFamily']) && is_string($fonts['fontFamily'])
            ? self::escapeCssFontFamilyList($fonts['fontFamily'])
            : self::escapeCssFontFamilyList((string) self::DEFAULT_FONTS['fontFamily']);

        $primary   = $c('colorPrimary');
        $mainText  = $c('colorMainText');
        $skeleton20 = self::escapeCssColorValue(self::colorWithOpacity($ecfColors['colorMainText'] ?? '#1A2C37', 0.2));
        $skeleton60 = self::escapeCssColorValue(self::colorWithOpacity($ecfColors['colorMainText'] ?? '#1A2C37', 0.6));
        $scroll30   = self::escapeCssColorValue(self::colorWithOpacity($ecfColors['colorPrimary'] ?? '#1246D6', 0.3));
        $scroll10   = self::escapeCssColorValue(self::colorWithOpacity($ecfColors['colorPrimary'] ?? '#1246D6', 0.1));

        $amVars = [
            '--am-c-primary'           => $primary,
            '--am-c-success'           => $c('colorSuccess'),
            '--am-c-error'             => $c('colorError'),
            '--am-c-warning'           => $c('colorWarning'),
            '--am-c-main-bgr'          => $c('colorMainBgr'),
            '--am-c-main-heading-text' => $c('colorMainHeadingText'),
            '--am-c-main-text'         => $mainText,
            '--am-c-sb-bgr'            => $c('colorSbBgr'),
            '--am-c-sb-text'           => $c('colorSbText'),
            '--am-c-inp-bgr'           => $c('colorInpBgr'),
            '--am-c-inp-border'        => $c('colorInpBorder'),
            '--am-c-inp-text'          => $c('colorInpText'),
            '--am-c-inp-placeholder'   => $c('colorInpPlaceHolder'),
            '--am-c-drop-bgr'          => $c('colorDropBgr'),
            '--am-c-drop-text'         => $c('colorDropText'),
            '--am-c-card-bgr'          => $c('colorCardBgr'),
            '--am-c-card-text'         => $c('colorCardText'),
            '--am-c-card-border'       => $c('colorCardBorder'),
            '--am-c-btn-prim'          => $c('colorBtnPrim'),
            '--am-c-btn-prim-text'     => $c('colorBtnPrimText'),
            '--am-c-btn-sec'           => $c('colorBtnSec'),
            '--am-c-btn-sec-text'      => $c('colorBtnSecText'),
            '--am-c-skeleton-op20'     => $skeleton20,
            '--am-c-skeleton-op60'     => $skeleton60,
            '--am-font-family'         => $fontFamily,
            '--am-dvh'                 => '100dvh',
            '--am-mw-main'             => '792px',
            '--am-hd-main'             => '652px',
            '--am-c-scroll-op30'       => $scroll30,
            '--am-c-scroll-op10'       => $scroll10,
            '--am-rad-inp'             => '6px',
            '--am-mb-dialog'           => '300px',
        ];

        $wpaAliases = self::buildWpaTokenAliases();

        $declarations = array_merge($amVars, $wpaAliases);
        $rules        = [];
        foreach ($declarations as $prop => $value) {
            $rules[] = sprintf('%s:%s;', $prop, $value);
        }

        return $fontFaceBlock . '.amelia-wpa{' . implode('', $rules) . '}';
    }

    /**
     * Point legacy WPA shell tokens at Amelia customize so existing wpa-events-single.css follows v3.
     *
     * @return array<string, string>
     */
    private static function buildWpaTokenAliases(): array
    {
        return [
            '--wpa-page-bg'   => 'var(--am-c-main-bgr)',
            '--wpa-shade-1000' => 'var(--am-c-main-text)',
            '--wpa-shade-900' => 'var(--am-c-main-heading-text)',
            '--wpa-shade-700' => 'var(--am-c-main-text)',
            '--wpa-shade-600' => 'var(--am-c-main-heading-text)',
            '--wpa-shade-500' => 'var(--am-c-inp-placeholder)',
            '--wpa-shade-400' => 'var(--am-c-main-heading-text)',
            '--wpa-shade-250' => 'var(--am-c-inp-border)',
            '--wpa-white'     => 'var(--am-c-inp-bgr)',
            '--wpa-blue-900'  => 'var(--am-c-primary)',
            '--wpa-blue-1000' => 'var(--am-c-btn-prim)',
            '--wpa-blue-600'  => 'var(--am-c-scroll-op30)',
            '--wpa-blue-500'  => 'var(--am-c-scroll-op10)',
            '--wpa-blue-400'  => 'var(--am-c-main-bgr)',
            '--wpa-open'      => 'var(--am-c-success)',
        ];
    }

    /**
     * @param array<string, string|bool> $fonts
     */
    private static function buildCustomFontFaceBlock(array $fonts): string
    {
        if (empty($fonts['customFontSelected'])) {
            return '';
        }

        $family = isset($fonts['fontFamily']) && is_string($fonts['fontFamily']) ? $fonts['fontFamily'] : '';
        $url    = isset($fonts['fontUrl']) && is_string($fonts['fontUrl']) ? $fonts['fontUrl'] : '';

        if ($family === '' || $url === '') {
            return '';
        }

        $safeUrl    = esc_url_raw($url);
        $safeFamily = preg_replace('/[^\x20-\x7E]/', '', $family);
        if ($safeFamily === '' || $safeUrl === '') {
            return '';
        }

        return '@font-face{font-family:\'' . esc_attr($safeFamily) . '\';src:url(' . $safeUrl . ');}';
    }

    private static function escapeCssColorValue(string $color): string
    {
        $trim = trim($color);
        if ($trim === '') {
            return 'transparent';
        }

        if (preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\))$/', $trim)) {
            return $trim;
        }

        return 'transparent';
    }

    private static function escapeCssFontFamilyList(string $family): string
    {
        $trim = trim(preg_replace('/\s+/', ' ', $family));
        if ($trim === '') {
            return 'sans-serif';
        }

        $parts = array_map('trim', explode(',', $trim));
        $out   = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(sans-serif|serif|monospace|cursive|fantasy|system-ui)$/i', $part)) {
                $out[] = strtolower($part);

                continue;
            }
            $quoted = str_replace(['\\', "'"], ['\\\\', "\\'"], $part);
            $out[]    = '\'' . $quoted . '\'';
        }

        return implode(',', $out) !== '' ? implode(',', $out) : 'sans-serif';
    }

    /**
     * @param float $opacity 0..1
     */
    private static function colorWithOpacity(string $color, float $opacity): string
    {
        $color = trim($color);
        if ($color === '') {
            return 'rgba(0,0,0,' . max(0.0, min(1.0, $opacity)) . ')';
        }

        if (stripos($color, 'rgb(') === 0) {
            $inner = substr($color, 4, -1);
            $parts = array_map('trim', explode(',', $inner));
            if (count($parts) >= 3) {
                return sprintf(
                    'rgba(%s,%s,%s,%s)',
                    $parts[0],
                    $parts[1],
                    $parts[2],
                    max(0.0, min(1.0, $opacity))
                );
            }
        }

        if (stripos($color, 'rgba(') === 0) {
            $inner = substr($color, 5, -1);
            $parts = array_map('trim', explode(',', $inner));
            if (count($parts) >= 3) {
                $a = count($parts) >= 4 && $opacity === 1.0 ? $parts[3] : (string) max(0.0, min(1.0, $opacity));

                return sprintf('rgba(%s,%s,%s,%s)', $parts[0], $parts[1], $parts[2], $a);
            }
        }

        if ($color[0] === '#') {
            $hex = substr($color, 1);
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            if (strlen($hex) === 8) {
                $hex = substr($hex, 0, 6);
            }
            if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
                return 'rgba(0,0,0,' . max(0.0, min(1.0, $opacity)) . ')';
            }
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));

            return sprintf(
                'rgba(%d,%d,%d,%s)',
                $r,
                $g,
                $b,
                max(0.0, min(1.0, $opacity))
            );
        }

        return $color;
    }
}
