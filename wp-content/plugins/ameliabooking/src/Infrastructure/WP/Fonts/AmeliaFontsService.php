<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\Fonts;

use AmeliaBooking\Domain\Services\Settings\SettingsService;

/**
 * Enqueues the bundled Google Fonts stylesheet for the public v3 forms.
 *
 * The forms used to render the `<link>` inside their own Vue template, which put one copy of the
 * whole 13-family `font.css` in the DOM per shortcode on the page. WordPress dedupes by handle, so
 * enqueueing here gives exactly one stylesheet no matter how many forms are rendered - and only the
 * family that customization actually selected.
 *
 * @package AmeliaBooking\Infrastructure\WP\Fonts
 */
class AmeliaFontsService
{
    /** @var string */
    public const STYLE_HANDLE = 'amelia_booking_fonts';

    /** @var string */
    private const FONTS_URL_PATH = 'v3/src/assets/scss/common/fonts/';

    /** @var string Matches `fonts.fontFamily` in v3/src/assets/js/common/defaultCustomize.js */
    private const DEFAULT_FONT_FAMILY = 'Amelia Roboto, sans-serif';

    /**
     * Primary family of the CSS font stack => stylesheet holding only that family's @font-face rules.
     * Mirrors FAMILY_STYLESHEETS in v3/src/assets/js/common/defaultFonts.js - keep both in sync with
     * the `fontOptions` list in redesign/src/views/customize/_components/settings/CustomizeBasic.vue.
     *
     * @var array<string, string>
     */
    private const FAMILY_STYLESHEETS = [
        'amelia roboto'          => 'font-roboto.css',
        'amelia lato'            => 'font-lato.css',
        'amelia merriweather'    => 'font-merriweather.css',
        'amelia montserrat'      => 'font-montserrat.css',
        'amelia mulish'          => 'font-mulish.css',
        'amelia nunito'          => 'font-nunito.css',
        'amelia asap condensed'  => 'font-asap-condensed.css',
        'amelia open sans'       => 'font-open-sans.css',
        'amelia barlow'          => 'font-barlow.css',
        'amelia oswald'          => 'font-oswald.css',
        'amelia bitter'          => 'font-bitter.css',
        'amelia poppins'         => 'font-poppins.css',
        'amelia carme'           => 'font-carme.css',
    ];

    /**
     * Enqueue the default font stylesheet once per page.
     *
     * Does nothing when the site uses an uploaded custom font - that one is injected as a single
     * @font-face rule by {@see v3/src/assets/js/public/customFontFace.js}.
     *
     * @param SettingsService $settingsService
     *
     * @return void
     */
    public static function enqueueDefaultFonts($settingsService): void
    {
        $fonts = self::getFontsSettings($settingsService);

        if (!empty($fonts['customFontSelected'])) {
            return;
        }

        $fontFamily = !empty($fonts['fontFamily']) && is_string($fonts['fontFamily'])
            ? $fonts['fontFamily']
            : self::DEFAULT_FONT_FAMILY;

        wp_enqueue_style(
            self::STYLE_HANDLE,
            AMELIA_URL . self::FONTS_URL_PATH . self::getStylesheetForFamily($fontFamily),
            [],
            AMELIA_VERSION
        );

        self::addPreconnectHint();
    }

    /**
     * @param SettingsService $settingsService
     *
     * @return array
     */
    private static function getFontsSettings($settingsService): array
    {
        $customizedData = $settingsService->getCategorySettings('customizedData');

        return !empty($customizedData['fonts']) && is_array($customizedData['fonts'])
            ? $customizedData['fonts']
            : [];
    }

    /**
     * Resolve a CSS font stack such as `Amelia Open Sans, sans-serif` to its single-family stylesheet.
     * Customization only ever writes one of the mapped families or switches to a custom font, so an
     * unmappable value means a hand-edited setting - fall back to the default family.
     *
     * @param string $fontFamily
     *
     * @return string
     */
    private static function getStylesheetForFamily($fontFamily): string
    {
        $primaryFamily = explode(',', $fontFamily)[0];

        $primaryFamily = strtolower(trim(trim($primaryFamily), '"\''));

        if (isset(self::FAMILY_STYLESHEETS[$primaryFamily])) {
            return self::FAMILY_STYLESHEETS[$primaryFamily];
        }

        return self::FAMILY_STYLESHEETS[strtolower(explode(',', self::DEFAULT_FONT_FAMILY)[0])];
    }

    /**
     * The stylesheet is served from the plugin, but every woff2 it points at comes from
     * fonts.gstatic.com - so that is the only host worth a preconnect. The forms used to also hint
     * fonts.googleapis.com, which nothing on the page ever requests.
     *
     * The hint is printed only if Amelia enqueues before `wp_head` reaches its resource hints. That
     * holds for block themes and for the WPA events landing, but a shortcode rendered late in the
     * content can miss it - in which case the filter is simply never called and nothing is emitted.
     *
     * @return void
     */
    private static function addPreconnectHint(): void
    {
        add_filter(
            'wp_resource_hints',
            function ($urls, $relationType) {
                if ($relationType === 'preconnect') {
                    $urls[] = [
                        'href'        => 'https://fonts.gstatic.com',
                        'crossorigin' => 'anonymous',
                    ];
                }

                return $urls;
            },
            10,
            2
        );
    }
}
