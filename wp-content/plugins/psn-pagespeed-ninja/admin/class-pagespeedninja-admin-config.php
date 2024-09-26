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

class PagespeedNinja_AdminConfig
{
    /**
     * @param array<string,string> $newConfig
     * @param array<string,string> $oldConfig
     * @return array
     */
    public function validate_config($newConfig, $oldConfig)
    {
        // Force update_option hook
        $newConfig['timestamp'] = time();

        if (is_network_admin()) {
            // Network config doesn't require Ressio options
            return $newConfig;
        }

        // copy other subset of settings
        foreach ($oldConfig as $preset_name => $value) {
            if (!isset($newConfig[$preset_name])) {
                $newConfig[$preset_name] = $value;
            }
        }

        // apply preset from post-install screen
        if (isset($_POST['pagespeedninja_preset'], $_POST['_wpnonce']) &&
            wp_verify_nonce($_POST['_wpnonce'], 'pagespeedninja_config-options')
        ) {

            $presets_list = file_get_contents(dirname(__DIR__) . '/includes/presets.json.php');
            $presets_list = explode("\n", $presets_list, 2)[1];
            $presets_list = str_replace("\\'", "'", $presets_list);
            $presets_list = json_decode($presets_list);

            $presets = array();
            foreach ($presets_list as $preset) {
                $presets[$preset->name] = array();
            }

            $options = file_get_contents(dirname(__DIR__) . '/includes/options.json.php');
            $options = explode("\n", $options, 2)[1];
            $options = str_replace("\\'", "'", $options);
            $options = json_decode($options);

            // use values suggested in PagespeedNinja_Activator
            $skip_presets = array('distribmode', 'caching', 'cdndomain');

            // load default presets
            foreach ($options as $section) {
                if (isset($section->items)) {
                    /** @var array<stdClass> {$section->items} */
                    foreach ($section->items as $item) {
                        if (isset($item->presets) && !in_array($item->name, $skip_presets, true)) {
                            foreach ($presets as $preset_name => $preset) {
                                $presets[$preset_name][$item->name] = $item->default;
                            }
                            foreach ((array)$item->presets as $preset_name => $option_value) {
                                $presets[$preset_name][$item->name] = $option_value;
                            }
                        }
                    }
                }
            }

            // load extra presets
            $extra_presets_dir = __DIR__ . '/extras/presets';
            $extra_presets_files = glob($extra_presets_dir . '/*.json');
            foreach ($extra_presets_files as $preset_file) {
                $preset_name = basename($preset_file, '.json');
                $preset_data = @file_get_contents($preset_file);
                $preset_data = @json_decode($preset_data);
                if (isset(
                    $preset_data->base,
                    $presets[$preset_data->base],
                    $preset_data->title,
                    $preset_data->tooltip,
                    $preset_data->options
                )) {
                    $preset = $presets[$preset_data->base];
                    foreach ($preset_data->options as $name => $value) {
                        $preset[$name] = $value;
                    }
                    $presets[$preset_name] = $preset;
                }
            }

            // apply selected preset
            $preset_name = $_POST['pagespeedninja_preset'];
            foreach ($presets[$preset_name] as $preset_name => $value) {
                $newConfig[$preset_name] = $value;
            }

            // check double gzip issue
            if ($newConfig['html_gzip']) {
                $url = get_home_url(null, '?pagespeedninja=no', 'http');
                $raw_response = wp_safe_remote_get($url, array(
                    'timeout' => 10,
                    'sslverify' => false,
                    'headers' => array(
                        'Accept-Encoding' => 'gzip, deflate',
                        'Connection' => 'close',
                    ),
                ));
                if (!empty(wp_remote_retrieve_header($raw_response, 'Content-Encoding'))) {
                    $newConfig['html_gzip'] = '0';
                }
            }
        }

        if (trim($newConfig['staticdir'], '/') === '') {
            // don't allow storing generated files in the website's root directory
            $newConfig['staticdir'] = '/s';
        }

        if (preg_match('#^(wp-(admin|include|content/(plugins|themes|upgrade))(/.*)?|(wp-content|wp-content/uploads|uploads)/?)$#', $newConfig['staticdir'])) {
            // don't allow to use standard WP's directories for staticdir (otherwise autoclean may remove core files)
            $newConfig['staticdir'] = '/s';
        }

        if ($newConfig['staticdir'][0] !== '/') {
            $newConfig['staticdir'] = '/' . $newConfig['staticdir'];
        }

        if (is_multisite()) {
            $global_config = get_site_option('pagespeedninja_config');
            $global_keys = array('distribmode', 'caching', 'caching_ttl', 'htaccess_caching', 'htaccess_gzip');
            foreach ($global_keys as $key) {
                $newConfig[$key] = $global_config[$key];
            }
        }
        $newConfig['ress_options'] = json_encode(self::generateRessConfig($newConfig));

        return $newConfig;
    }

    /**
     * @param array<string,string> $options
     * @return array<string,mixed>
     */
    public static function generateRessConfig($options)
    {
        $ress_options = array(
            'disable_autoload' => true,
            'cachefast' => false,
            'html' => array(
                'mergespace' => false,
                'removecomments' => false,
                'urlminify' => false,
                'gzlevel' => 0,
                'sortattr' => false,
                'removedefattr' => false,
                'removeiecond' => false,
            ),
            'css' => array(
                'mergeheadbody' => false,
                'merge' => false,
                'mergeinline' => false,
                'crossfileoptimization' => false,
                'inlinelimit' => 0,
                'checklinkattributes' => true,
                'checkstyleattributes' => true,
                'minifyattribute' => false,
            ),
            'js' => array(
                'mergeheadbody' => false,
                'merge' => false,
                'mergeinline' => false,
                'automove' => false,
                'forceasync' => false,
                'forcedefer' => false,
                'crossfileoptimization' => false,
                'inlinelimit' => 0,
                'wraptrycatch' => true,
                'checkattributes' => true,
                'minifyattribute' => false,
                'skipinits' => false,
            ),
            'img' => array(
                'minify' => false,
                'minifyrescaled' => false,
                'webp' => false,
                'avif' => false,
                'jpegquality' => 100,
                'webpquality' => 100,
                'avifquality' => 100,
                'execoptim' => array(),
            ),
            'di' => array(
                'deviceDetector' => Ressio_DeviceDetector_Wordpress::class,
                'cssMinify' => Ressio_CssMinify_None::class,
                'jsMinify' => Ressio_JsMinify_None::class,
                'imgOptimizer.gif' => Ressio_ImgHandler_GD::class,
                'imgOptimizer.jpg' => Ressio_ImgHandler_GD::class,
                'imgOptimizer.png' => Ressio_ImgHandler_GD::class,
                'imgOptimizer.webp' => Ressio_ImgHandler_GD::class,
                'imgOptimizer.avif' => Ressio_ImgHandler_GD::class,
                'imgOptimizer.svg' => Ressio_ImgHandler_SvgGz::class,
                'imgOptimizer.bmp' => Ressio_ImgHandler_GD::class,
                'worker' => Ressio_Worker_SyncOnly::class,
            ),
        );

        $webrooturi = parse_url(get_option('siteurl'), PHP_URL_PATH);
        if ($webrooturi === null) {
            $webrooturi = '';
        }

        $ress_options = array_merge($ress_options, array(
            'webrootpath' => rtrim(ABSPATH, '/'),
            'webrooturi' => $webrooturi,
            'cachedir' => WP_CONTENT_DIR . '/uploads/psn-pagespeed-ninja/cache',
            'staticdir' => $options['staticdir'],
            'fileloader' => ($options['distribmode'] === 'php') ? 'php' : 'file',
            'fileloaderphppath' => rtrim(ABSPATH, '/') . $options['staticdir'] . '/f.php',
            'logginglevel' => (int)$options['ress_logginglevel'],
            'use_symlink' => (bool)$options['use_symlink'],
        ));

        $ress_options['html']['rules_safe_exclude'] = self::listToRules($options['html_rules_safe_exclude']);
        $ttl = (int)$options['caching_ttl'] * 60;
        $ress_options['cachettl'] = $ttl; // max(24 * 60 * 60, $ttl);

        switch ($options['htmloptimizer']) {
            case 'pharse':
                $ress_options['di']['htmlOptimizer'] = Ressio_HtmlOptimizer_Pharse::class;
                break;
            case 'stream':
                $ress_options['di']['htmlOptimizer'] = Ressio_HtmlOptimizer_Stream::class;
                break;
            case 'streamfull':
                $ress_options['di']['htmlOptimizer'] = Ressio_HtmlOptimizer_StreamFull::class;
                break;
            case 'dom':
                $ress_options['di']['htmlOptimizer'] = Ressio_HtmlOptimizer_Dom::class;
                break;
            default:
                trigger_error('PageSpeed Ninja: unknown html optimizer value: ' . var_export($options['htmloptimizer'], true));
        }

        if ($options['psi_server-response-time']) {
            $ress_options['cachefast'] = (bool)$options['caching_processed'];
        }

        if ($options['psi_uses-text-compression']) {
            $ress_options['html']['gzlevel'] = $options['html_gzip'] ? 5 : 0;
            $ress_options['html']['sortattr'] = (bool)$options['html_sortattr'];
        }

        if ($options['psi_uses-rel-preconnect']) {
            if ($options['dnsprefetch']) {
                $domains = $options['dnsprefetch_domain'];
                $ress_options['plugins'][Ressio_Plugin_DNSPrefetch::class] = array(
                    'domains' => self::split($domains),
                );
            }
        }

        if ($options['psi_unminified-css']) {
            switch ($options['css_di_cssMinify']) {
                case 'none':
                    $ress_options['di']['cssMinify'] = Ressio_CssMinify_None::class;
                    break;
                case 'ress':
                case 'csstidy':
                case 'both':
                    $ress_options['di']['cssMinify'] = Ressio_CssMinify_Simple::class;
                    break;
                case 'exec':
                    $ress_options['di']['cssMinify'] = Ressio_CssMinify_Exec::class;
                    break;
                default:
                    trigger_error('PageSpeed Ninja: unknown css_di_cssMinify value ' . var_export($options['css_di_cssMinify'], true));
            }
            $ress_options['css']['minifyattribute'] = (bool)$options['css_minifyattribute'];
            $ress_options['css']['crossfileoptimization'] = (bool)$options['css_crossfileoptimization'];
        }

        //if ($options['psi_unused-css-rules']) {}

        if ($options['psi_unminified-javascript']) {
            switch ($options['js_di_jsMinify']) {
                case 'none':
                    $ress_options['di']['jsMinify'] = Ressio_JsMinify_None::class;
                    break;
                case 'jsmin':
                    $ress_options['di']['jsMinify'] = Ressio_JsMinify_JsMin::class;
                    break;
                case 'exec':
                    $ress_options['di']['jsMinify'] = Ressio_JsMinify_Exec::class;
                    break;
                default:
                    trigger_error('PageSpeed Ninja: unknown js_di_jsMinify value ' . var_export($options['js_di_jsMinify'], true));
            }
            $ress_options['js']['minifyattribute'] = (bool)$options['js_minifyattribute'];
            $ress_options['js']['crossfileoptimization'] = (bool)$options['js_crossfileoptimization'];
        }

        if ($options['psi_render-blocking-resources']) {
            if ($options['css_inlinefirsttime'] || $options['js_inlinefirsttime']) {
                $ress_options['plugins'][Ressio_Plugin_InlineJsCss::class] = array(
                    'cookie' => 'psn_visitor',
                    'css' => $options['css_inlinefirsttime'],
                    'js' => $options['js_inlinefirsttime'],
                );
            }
            if ($options['css_nonblockjs']) {
                $ress_options['plugins'][Ressio_Plugin_NonBlockJS::class] = array();
            }
        }

        if ($options['psi_font-display']) {
            switch ($options['css_googlefonts']) {
                case 'swap':
                case 'fout':
                case 'async':
                    $ress_options['plugins'][Ressio_Plugin_GoogleFont::class] = array('method' => 'swap');
                    break;
                case 'auto':
                case 'foit':
                case 'first':
                case 'sync':
                    $ress_options['plugins'][Ressio_Plugin_GoogleFont::class] = array('method' => 'auto');
                    break;
                case 'block':
                    $ress_options['plugins'][Ressio_Plugin_GoogleFont::class] = array('method' => 'block');
                    break;
                case 'fallback':
                    $ress_options['plugins'][Ressio_Plugin_GoogleFont::class] = array('method' => 'fallback');
                    break;
                case 'optional':
                    $ress_options['plugins'][Ressio_Plugin_GoogleFont::class] = array('method' => 'optional');
                    break;
                case 'none':
                    break;
            }
            if ($options['css_fontdisplay']) {
                $ress_options['plugins'][Ressio_Plugin_FontDisplaySwap::class] = array(
                    'display' => $options['css_fontdisplay'],
                    'excludedFonts' => self::split($options['css_fontdisplayswap_exclude'])
                );
            }
        }

        //if ($options['psi_redirects']) {}

        if ($options['psi_total-byte-weight']) {
            $ress_options['html']['mergespace'] = (bool)$options['html_mergespace'];
            $ress_options['html']['removecomments'] = (bool)$options['html_removecomments'];
            $ress_options['html']['urlminify'] = (bool)$options['html_minifyurl'];
            $ress_options['html']['removedefattr'] = (bool)$options['html_removedefattr'];
            $ress_options['html']['removeiecond'] = (bool)$options['html_removeiecond'];

            $ress_options['css']['merge'] = (bool)$options['css_merge'];
            $ress_options['css']['mergeheadbody'] = true;
            if ($options['css_mergeinline'] === 'head') {
                $ress_options['css']['mergeinline'] = 'head';
            } else {
                $ress_options['css']['mergeinline'] = (bool)$options['css_mergeinline'];
            }
            $ress_options['css']['inlinelimit'] = (int)$options['css_inlinelimit'];
            $ress_options['css']['checklinkattributes'] = (bool)$options['css_checklinkattributes'];
            $ress_options['css']['checkstyleattributes'] = (bool)$options['css_checkstyleattributes'];

            $ress_options['css']['rules_merge_bypass'] = self::listToRules($options['css_rules_merge_bypass']);
            $ress_options['css']['rules_merge_stop'] = self::listToRules($options['css_rules_merge_stop']);
            $ress_options['css']['rules_merge_exclude'] = self::listToRules($options['css_rules_merge_exclude']);
            $ress_options['css']['rules_merge_include'] = self::listToRules($options['css_rules_merge_include']);
            $ress_options['css']['rules_merge_startgroup'] = self::listToRules($options['css_rules_merge_startgroup']);
            $ress_options['css']['rules_merge_stopgroup'] = self::listToRules($options['css_rules_merge_stopgroup']);
            $ress_options['css']['rules_minify_exclude'] = self::listToRules($options['css_rules_minify_exclude']);

            $ress_options['js']['merge'] = (bool)$options['js_merge'];
            $ress_options['js']['mergeheadbody'] = true;
            if ($options['js_mergeinline'] === 'head') {
                $ress_options['js']['mergeinline'] = 'head';
            } else {
                $ress_options['js']['mergeinline'] = (bool)$options['js_mergeinline'];
            }
            $ress_options['js']['automove'] = (bool)$options['js_automove'];
            $ress_options['js']['forcedefer'] = (bool)$options['js_forcedefer'];
            $ress_options['js']['forceasync'] = (bool)$options['js_forceasync'];
            $ress_options['js']['inlinelimit'] = (int)$options['js_inlinelimit'];
            $ress_options['js']['wraptrycatch'] = (bool)$options['js_wraptrycatch'];
            $ress_options['js']['checkattributes'] = (bool)$options['js_checkattributes'];
            $ress_options['js']['skipinits'] = (bool)$options['js_skipinits'];

            $ress_options['js']['rules_merge_bypass'] = self::listToRules($options['js_rules_merge_bypass']);
            $ress_options['js']['rules_merge_stop'] = self::listToRules($options['js_rules_merge_stop']);
            $ress_options['js']['rules_merge_exclude'] = self::listToRules($options['js_rules_merge_exclude']);
            $ress_options['js']['rules_merge_include'] = self::listToRules($options['js_rules_merge_include']);
            $ress_options['js']['rules_merge_startgroup'] = self::listToRules($options['js_rules_merge_startgroup']);
            $ress_options['js']['rules_merge_stopgroup'] = self::listToRules($options['js_rules_merge_stopgroup']);
            $ress_options['js']['rules_move_exclude'] = self::listToRules($options['js_rules_move_exclude']);
            $ress_options['js']['rules_async_exclude'] = self::listToRules($options['js_rules_async_exclude']);
            $ress_options['js']['rules_async_include'] = self::listToRules($options['js_rules_async_include']);
            $ress_options['js']['rules_defer_exclude'] = self::listToRules($options['js_rules_defer_exclude']);
            $ress_options['js']['rules_defer_include'] = self::listToRules($options['js_rules_defer_include']);
            $ress_options['js']['rules_minify_exclude'] = self::listToRules($options['js_rules_minify_exclude']);
        }

        $ress_options['di']['imgOptimizer.gif'] = Ressio_ImgHandler_GD::class;
        $ress_options['di']['imgOptimizer.jpg'] = Ressio_ImgHandler_GD::class;
        $ress_options['di']['imgOptimizer.png'] = Ressio_ImgHandler_GD::class;
        $ress_options['di']['imgOptimizer.webp'] = Ressio_ImgHandler_GD::class;
        $ress_options['di']['imgOptimizer.avif'] = Ressio_ImgHandler_GD::class;
        $ress_options['di']['imgOptimizer.svg'] = Ressio_ImgHandler_SvgGz::class;
        $ress_options['img']['jpegquality'] = (int)$options['img_jpegquality'];
        $ress_options['img']['webpquality'] = (int)$options['img_webpquality'];
        $ress_options['img']['avifquality'] = (int)$options['img_avifquality'];

        if ($options['psi_uses-optimized-images']) {
            $ress_options['img']['minify'] = $options['img_minify'];
            $ress_options['img']['rules_minify_exclude'] = self::listToRules($options['img_rules_minify_exclude']);
        }

        if ($options['psi_modern-image-format']) {
            $ress_options['img']['webp'] = (bool)$options['img_webp'];
            // check WebP is actually supported
            if ($options['img_webp']) {
                include_once dirname(__DIR__) . '/ress/ressio.php';
                Ressio::registerAutoloading();
                $handler = new Ressio_ImgHandler_GD(new Ressio_DI());
                if (!$handler->isSupportedFormat('webp')) {
                    $ress_options['img']['webp'] = false;
                }
            }
        }

        if ($options['psi_unsized-images']) {
            if ($options['img_size']) {
                $ress_options['plugins'][Ressio_Plugin_Imagesize::class] = null;
            }
        }

        if ($options['psi_offscreen-images']) {
            if (!empty($options['lazyload_method']) && ($options['img_lazyload'] || $options['img_lazyload_video'] || $options['img_lazyload_iframe'])) {
                $ress_options['plugins'][Ressio_Plugin_Lazyload::class] = array(
                    'method' => $options['lazyload_method'],
                    'image' => (bool)$options['img_lazyload'],
                    'video' => (bool)$options['img_lazyload_video'],
                    'iframe' => (bool)$options['img_lazyload_iframe'],
                    'lqip' => $options['img_lazyload_lqip'],
                    'lqip_embed' => (bool)$options['img_lazyload_embed'],
                    'edgey' => (int)$options['img_lazyload_edgey'],
                    'noscriptpos' => $options['img_lazyload_noscript'],
                    'skipimages' => (int)$options['img_lazyload_skip'],
                    'rules_img_exclude' => self::listToRules($options['lazyload_rules_img_exclude']),
                    'rules_video_exclude' => self::listToRules($options['lazyload_rules_video_exclude']),
                    'rules_iframe_exclude' => self::listToRules($options['lazyload_rules_iframe_exclude']),
                );
                if (isset($options['lazyload_rules_img_bg_exclude'])) {
                    $ress_options['plugins'][Ressio_Plugin_Lazyload::class]['rules_bg_exclude'] = self::listToRules($options['lazyload_rules_img_bg_exclude']);
                }
            }
        }

        //if ($options['psi_lcp-lazy-loaded']) {}
        //if ($options['psi_prioritize-lcp-image']) {}
        //if ($options['psi_unused-javascript']) {}
        //if ($options['psi_efficient-animated-content']) {}
        //if ($options['psi_unsized-images']) {}
        //if ($options['psi_non-composited-animations']) {}

        if ($options['psi_bootup-time']) {
            if ($options['js_widgets'] || $options['js_widgets_delay_async'] || $options['js_widgets_delay_scripts']) {
                $ress_options['plugins'][Ressio_Plugin_Widgets::class] = array(
                    'delay_widgets' => (bool)$options['js_widgets'],
                    'delay_async_js' => (bool)$options['js_widgets_delay_async'],
                    'delay_scripts' => self::split($options['js_widgets_delay_scripts_list'])
                );
            }
        }

        if ($options['psi_dom-size']) {
            // remove_objects
        }

        if ($options['psi_viewport']) {
            $ress_options['plugins'][Ressio_Plugin_ViewportMetaTag::class] = array(
                'viewport' => 'width=' . (empty($options['viewport_width']) ? 'device-width' : $options['viewport_width'])
            );
        }

        if ($options['psi_legacy-javascript']) {
            $regexps = array();
            $lines = self::split($options['js_polyfills']);
            foreach ($lines as $line) {
                $regexps[] = preg_quote($line, '@');
            }
            $ress_options['plugins'][Ressio_Plugin_LegacyPolyfill::class] = array(
                'regex' => '@' . implode('|', $regexps) . '@'
            );
        }

        if ($options['psi_uses-rel-preload']) {
            // Note: preload should be the last loaded plugin
            $preload_style = $options['preload_style'];
            $preload_font = $options['preload_font'];
            $preload_script = $options['preload_script'];
            $preload_module = $options['preload_module'];
            $preload_image = $options['preload_image'];
            $ress_options['plugins'][Ressio_Plugin_Preload::class] = array(
                'linkheader' => false,
                'style' => self::split($preload_style),
                'font' => self::split($preload_font),
                'script' => self::split($preload_script),
                'module' => self::split($preload_module),
                'image' => self::split($preload_image),
            );
        }
        //if ($options['psi_uses-passive-event-listeners']) {} //<= available in Pro
        //if ($options['psi_duplicated-javascript']) {}
        //if ($options['psi_third-party-summary']) {}
        //if ($options['psi_third-party-facades']) {}
        //if ($options['psi_mainthread-work-breakdown']) {}
        //if ($options['psi_no-document-write']) {}
        //if ($options['psi_interactive']) {}
        //if ($options['psi_max-potential-fid']) {}
        $ress_options = apply_filters('psn_prepare_ressio_config', $ress_options, $options);

        return $ress_options;
    }

    /**
     * @param array $oldConfig
     * @param array $newConfig
     */
    public function update_config($oldConfig, $newConfig)
    {


        if (
            isset($newConfig['afterinstall_popup']) &&
            $newConfig['afterinstall_popup'] === '1' &&
            (!is_multisite() || is_network_admin())
        ) {
            $pluginDir = dirname(__DIR__);
            $srcDir = $pluginDir . '/assets/sample';
            $homeDir = rtrim(ABSPATH, '/');

            if (!is_multisite()) {
                $staticdirs = array($newConfig['staticdir']);
            } else {
                $staticdirs = array();
                foreach (get_sites() as $site) {
                    $config = get_blog_option($site->blog_id, 'pagespeedninja_config');
                    if (isset($config['staticdir'])) {
                        $staticdirs[$config['staticdir']] = 1;
                    }
                }
                $staticdirs = array_keys($staticdirs);
            }

            foreach ($staticdirs as $staticdir) {
                if ($staticdir === '') {
                    continue;
                }

                $destDir = $homeDir . $staticdir;
                if (!is_dir($destDir)) {
                    if (!@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
                        trigger_error('PageSpeed Ninja: cannot create directory ' . var_export($destDir, true));
                    } else {
                        copy($srcDir . '/sample_readme.txt', $destDir . '/readme.txt');
                    }
                }

                $staticHtaccess = $destDir . '/.htaccess';
                switch ($newConfig['distribmode']) {
                    case 'direct':
                        if (file_exists($staticHtaccess)) {
                            @unlink($staticHtaccess);
                        }
                        break;
                    case 'apache':
                        copy($srcDir . '/sample_apache_static.htaccess', $staticHtaccess);
                        break;
                    case 'rewrite':
                        copy($srcDir . '/sample_php.htaccess', $staticHtaccess);
                        $this->copyGetPhp($srcDir, $destDir);
                        break;
                    case 'php':
                        if (file_exists($staticHtaccess)) {
                            @unlink($staticHtaccess);
                        }
                        $this->copyGetPhp($srcDir, $destDir);
                        break;
                    default:
                        trigger_error('PageSpeed Ninja: unknown distribmode value ' . var_export($newConfig['distribmode'], true));
                }

                $htaccess =
                    file_get_contents($srcDir . '/sample_apache_static_subdir.htaccess') . "\n" .
                    file_get_contents($srcDir . '/gzip.htaccess') . "\n" .
                    file_get_contents($srcDir . '/cache.htaccess') . "\n";
                $subdirs = array('img', 'img-r', 'img-lqip', 'loaded');
                foreach ($subdirs as $subdir) {
                    @mkdir("$destDir/$subdir", 0775, true);
                    file_put_contents("$destDir/$subdir/.htaccess", $htaccess);
                }
            }

            $caching = defined('WP_CACHE') && WP_CACHE;
            if ((is_network_admin() || $newConfig['psi_server-response-time']) && $newConfig['caching']) {
                $dataDir = WP_CONTENT_DIR . '/uploads/psn-pagespeed-ninja';
                $cache_dir = $dataDir . '/pagecache';
                if (!is_dir($cache_dir)) {
                    @mkdir($cache_dir, 0775, true);
                    @copy("$pluginDir/assets/sample/sample_apache_denied.htaccess", "$cache_dir/.htaccess");
                }

                $deviceDependent_webp = is_network_admin() || ($newConfig['psi_modern-image-format'] && $newConfig['img_webp']);
                $deviceDependent_avif = is_network_admin() || ($newConfig['psi_modern-image-format'] && $newConfig['img_avif']);
                $deviceDependent_vendor = is_network_admin() ||
                    ($newConfig['psi_total-byte-weight'] && $newConfig['html_removeiecond']) ||
                    ($newConfig['psi_server-response-time'] && $newConfig['pagecache_devicedependent']);

                $disable_queries = $newConfig['pagecache_disable_queries'];

                $params_skip = str_replace(array("\r", "\n\n"), array("\n", "\n"), trim($newConfig['pagecache_params_skip']));

                $cookies_disable = str_replace(array("\r", "\n\n"), array("\n", "\n"), trim($newConfig['pagecache_cookies_disable']));
                $cookies_disable = explode("\n", $cookies_disable);
                $cookies_disable_std = array();
                if (defined('LOGGED_IN_COOKIE')) {
                    $cookies_disable_std[] = LOGGED_IN_COOKIE;
                }
                if (defined('COOKIEHASH')) {
                    $cookies_disable_std[] = 'comment_author_' . COOKIEHASH;
                }
                $cookies_disable = array_merge($cookies_disable, $cookies_disable_std);
                $cookies_disable = implode("\n", $cookies_disable);

                $cookies_depend = str_replace(array("\r", "\n\n"), array("\n", "\n"), trim($newConfig['pagecache_cookies_depend']));
                $cookies_depend = explode("\n", $cookies_depend);
                $cookies_depend_std = array();
                $cookies_depend = array_merge($cookies_depend, $cookies_depend_std);
                $cookies_depend = implode("\n", $cookies_depend);

                $http_headers_depend = str_replace(array("\r", "\n\n"), array("\n", "\n"), trim($newConfig['pagecache_http_depend']));
                $http_headers_depend = explode("\n", $http_headers_depend);
                foreach ($http_headers_depend as $i => $http_header_name) {
                    if ($http_header_name !== '') {
                        $http_headers_depend[$i] = 'HTTP_' . strtoupper(strtr(trim($http_header_name), '-', '_'));
                    }
                }
                $http_headers_depend = implode("\n", $http_headers_depend);

                $exclude_urls = str_replace(array("\r", "\n\n"), array("\n", "\n"), trim($newConfig['pagecache_exclude_urls']));

                $advanced_cache_defines =
                    "<?php\n" .
                    "/* PageSpeed Ninja Caching */\n" .
                    "defined('ABSPATH') || die();\n" .
                    "define('PAGESPEEDNINJA_CACHE_DIR', '$cache_dir');\n" .
                    "define('PAGESPEEDNINJA_CACHE_PLUGIN', '$pluginDir');\n" .
                    "define('PAGESPEEDNINJA_CACHE_RESSDIR', '$pluginDir/ress');\n" .
                    "define('PAGESPEEDNINJA_CACHE_DEPS_WEBP', " . ($deviceDependent_webp ? 'true' : 'false') . ");\n" .
                    "define('PAGESPEEDNINJA_CACHE_DEPS_AVIF', " . ($deviceDependent_avif ? 'true' : 'false') . ");\n" .
                    "define('PAGESPEEDNINJA_CACHE_DEPS_VENDOR', " . ($deviceDependent_vendor ? 'true' : 'false') . ");\n" .
                    "define('PAGESPEEDNINJA_CACHE_TTL', " . ($newConfig['caching_ttl'] * 60) . ");\n" .
                    "define('PAGESPEEDNINJA_CACHE_DISABLE_QUERIES', " . ($disable_queries ? 'true' : 'false') . ");\n" .
                    "define('PAGESPEEDNINJA_CACHE_PARAMS_SKIP', " . var_export($params_skip, true) . ");\n" .
                    "define('PAGESPEEDNINJA_CACHE_COOKIES_DISABLE', " . var_export($cookies_disable, true) . ");\n" .
                    "define('PAGESPEEDNINJA_CACHE_COOKIES_DEPEND', " . var_export($cookies_depend, true) . ");\n" .
                    "define('PAGESPEEDNINJA_CACHE_HTTP_DEPEND', " . var_export($http_headers_depend, true) . ");\n" .
                    "define('PAGESPEEDNINJA_CACHE_EXCLUDE_URLS', " . var_export($exclude_urls, true) . ");\n";
                $advanced_cache =
                    "<?php\n" .
                    "/* PageSpeed Ninja Caching */\n" .
                    "defined('ABSPATH') || die();\n" .
                    "if (is_file('$dataDir/advanced-cache-defines.php')) {\n" .
                    "  include_once '$dataDir/advanced-cache-defines.php';\n" .
                    "  include '$pluginDir/public/advanced-cache.php';\n" .
                    "}\n";
                file_put_contents($dataDir . '/advanced-cache-defines.php', $advanced_cache_defines, LOCK_EX);
                file_put_contents(WP_CONTENT_DIR . '/advanced-cache.php', $advanced_cache, LOCK_EX);
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($dataDir . '/advanced-cache-defines.php');
                    opcache_invalidate(WP_CONTENT_DIR . '/advanced-cache.php');
                }

                if (!$caching) {
                    $this->update_WP_CACHE(true);
                }
            } else {
                if ($caching) {
                    $this->update_WP_CACHE(false);
                }
            }
            $htaccess = '';
            if ((is_network_admin() || $newConfig['psi_uses-text-compression']) && $newConfig['htaccess_gzip']) {
                $htaccess .= file_get_contents($pluginDir . '/assets/sample/gzip.htaccess') . "\n";
            }
            if ((is_network_admin() || $newConfig['psi_uses-long-cache-ttl']) && $newConfig['htaccess_caching']) {
                $htaccess .= file_get_contents($pluginDir . '/assets/sample/cache.htaccess') . "\n";
            }
            $marker = 'Page Speed Ninja';
            $dirs = array(
                'wp-includes',
                'wp-content',
                'uploads'
            );
            if (!isset($GLOBALS['wp_locale_switcher'])) {
                // a fix for insert_with_markers to work during deactivation stage
                $GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
                $GLOBALS['wp_locale_switcher']->init();
            }
            foreach ($dirs as $dir) {
                if (is_dir($homeDir . '/' . $dir)) {
                    insert_with_markers($homeDir . '/' . $dir . '/.htaccess', $marker, $htaccess);
                }
            }

            if (isset($newConfig['autoupdate'])) {
                $new_autoupdate = (bool)$newConfig['autoupdate'];
                $plugin = 'psn-pagespeed-ninja/pagespeedninja.php';
                $auto_updates = (array) get_site_option('auto_update_plugins', array());
                $current_autoupdate = in_array($plugin, $auto_updates, true);
                if ($new_autoupdate !== $current_autoupdate) {
                    if ($new_autoupdate) {
                        $auto_updates[] = $plugin;
                    } else {
                        $auto_updates = array_diff($auto_updates, array($plugin));
                    }
                    update_site_option('auto_update_plugins', $auto_updates);
                }
            }
        }

        $safeSettings = array('errorlogging', 'html_gzip', 'htaccess_gzip', 'afterinstall_popup',
            'htaccess_caching', 'caching', 'caching_processed', 'caching_ttl', 'css_abovethefoldcookie',
            'css_abovethefoldautoupdate', 'img_driver', 'img_jpegquality', 'img_webpquality', 'img_avifquality',
            'version');
        foreach ($newConfig as $name => $value) {
            if (isset($oldConfig[$name]) && $value !== $oldConfig[$name] && !in_array($name, $safeSettings, true)) {
                $pagecache_stamp = WP_CONTENT_DIR . '/uploads/psn-pagespeed-ninja/pagecache/tags/GLOBAL';
                @touch($pagecache_stamp);
                break;
            }
        }

        $workers_stamp = WP_CONTENT_DIR . '/uploads/psn-pagespeed-ninja/cache/worker_config.stamp';
        @touch($workers_stamp);

        if (!is_network_admin() && $newConfig['afterinstall_popup'] === '1' && $newConfig['allow_ext_stats']) {
            global $wp_version, $wpdb;
            // get active plugins
            $plugins = array();
            foreach (get_option('active_plugins') as $plugin) {
                $plugin_data = get_file_data(WP_PLUGIN_DIR . '/' . $plugin, array('Version' => 'Version'));
                $plugins[$plugin] = $plugin_data['Version'];
            }
            // clean up config data
            $config = $newConfig;
            unset($config['css_abovethefoldstyle'], $config['ress_options']);
            // prepare data
            $data = array(
                'hash' => sha1(get_option('siteurl')),
                'wp' => $wp_version,
                'php' => PHP_VERSION,
                'mysql' => $wpdb->db_version(),
                'multisite' => is_multisite(),
                'plugins' => json_encode($plugins),
                'theme' => get_option('template'),
                'config' => $config,
                'scores' => get_transient('pagespeedninja_psi_scores')
            );
            include_once ABSPATH . 'wp-admin/includes/admin.php';
            wp_safe_remote_post('https://api.pagespeed.ninja/v1/savestats', array('body' => $data));
        }

        if ($newConfig['apikey'] !== '' && (!isset($oldConfig['apikey']) || $newConfig['apikey'] !== $oldConfig['apikey'])) {
            $data = array(
                'apikey' => $newConfig['apikey']
            );
            include_once ABSPATH . 'wp-admin/includes/admin.php';
            wp_safe_remote_post('https://api.pagespeed.ninja/v1/activateapikey', array('body' => $data));
        }
    }

    /**
     * @param string $srcDir
     * @param string $destDir
     * @return void
     */
    private function copyGetPhp($srcDir, $destDir)
    {
        $plugin_dir = basename(dirname(__DIR__));

        $content = file_get_contents($srcDir . '/f.php.sample');
        $content = preg_replace(
            array('/^\$root\s*=.*?$/m', '/^\$plugin_root\s*=.*?$/m'),
            array("\$root = '" . ABSPATH . "';", "\$plugin_root = \$root . 'wp-content/plugins/$plugin_dir';"),
            $content
        );
        file_put_contents($destDir . '/f.php', $content, LOCK_EX);
    }

    /**
     * @param bool $enabled
     * @return void
     */
    private function update_WP_CACHE($enabled)
    {
        $file = ABSPATH . 'wp-config.php';
        if (!file_exists($file)) {
            $file = dirname(ABSPATH) . '/wp-config.php';
        }

        $config = file_get_contents($file);
        $config = preg_replace('/^\s*define\s*\(\s*[\'"]WP_CACHE[\'"]\s*,[^)]+\)\s*;\s*(?:\/\/.*?)?(?>\r\n|\n|\r)/m', '', $config);
        if ($enabled) {
            $config = preg_replace('/^<\?php\b/', "<?php\ndefine('WP_CACHE', true);", $config);
        }
        @file_put_contents($file, $config, LOCK_EX);
    }

    /**
     * @param string $source
     * @return ?array
     */
    private static function listToRules($source)
    {
        $regexp_content = array();
        $regexp_attrs = array();

        $lines = explode("\n", $source);
        foreach ($lines as $line) {
            if (!preg_match('/^([\w\s-]*)([*~^$]?=)(.*)$/', $line, $matches)) {
                continue;
            }
            $attr = strtolower(trim($matches[1]));
            $cond = $matches[2];
            $value = trim($matches[3]);
            switch ($cond) {
                case '~=':
                    $value = str_replace('@', '\@', $value);
                    if (preg_match("@$value@", '') === false) {
                        continue 2;
                    }
                    break;
                case '=':
                    $value = '^' . preg_quote($value, '@') . '$';
                    break;
                case '*=':
                    $value = preg_quote($value, '@');
                    break;
                case '^=':
                    $value = '^' . preg_quote($value, '@');
                    break;
                case '$=':
                    $value = preg_quote($value, '@') . '$';
                    break;
            }
            if ($attr === '') {
                $regexp_content[] = $value;
            } else {
                if (!isset($regexp_attrs[$attr])) {
                    $regexp_attrs[$attr] = array();
                }
                $regexp_attrs[$attr][] = $value;
            }
        }

        $result = array();
        if (count($regexp_content)) {
            $result['content'] = '@' . implode('|', $regexp_content) . '@';
        }
        if (count($regexp_attrs)) {
            $result['attrs'] = array();
            foreach ($regexp_attrs as $attr => $rules) {
                $result['attrs'][$attr] = '@' . implode('|', $rules) . '@';
            }
        }

        return count($result) ? $result : null;
    }

    /**
     * @param string $str
     * @return string[]
     */
    private static function split($str)
    {
        return array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $str)));
    }
}
