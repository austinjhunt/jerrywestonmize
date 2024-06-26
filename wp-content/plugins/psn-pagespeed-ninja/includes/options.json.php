<?php defined('ABSPATH') || die(); ?>
[
  {
    "title": "<?php _e('General', 'psn-pagespeed-ninja'); ?>",
    "items": [
      {
        "name": "email",
        "global": 1,
        "title": "<?php _e('Email', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Your e-mail in order to keep in touch.', 'psn-pagespeed-ninja'); ?>",
        "type": "text",
        "default": ""
      },
      {
        "name": "apikey",
        "global": 1,
        "title": "<?php _e('License Key', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('The License key is used for authentication on the pagespeed.ninja servers (to generate critical CSS styles, download updates, etc).', 'psn-pagespeed-ninja'); ?>",
        "type": "apikey",
        "default": ""
      },
      {
        "global": 1,
        "title": "<?php _e('Subscription', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Status of your subscription.', 'psn-pagespeed-ninja'); ?>",
        "type": "do_subscription"
      },
      {
        "title": "<?php _e('Backup', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('You can back up your PageSpeed Ninja settings.', 'psn-pagespeed-ninja'); ?>",
        "type": "do_backup",
        "pro": 1
      },
      {
        "title": "<?php _e('Restore', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('You can restore your PageSpeed Ninja settings.', 'psn-pagespeed-ninja'); ?>",
        "type": "do_restore",
        "pro": 1
      },
      {
        "name": "subsection_general",
        "title": "<?php _e('General', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "",
        "type": "subsection"
      },
      {
        "name": "autoupdate",
        "global": 1,
        "title": "<?php _e('Auto Update', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('PageSpeed Ninja plugin will be updated automatically.', 'psn-pagespeed-ninja'); ?>",
        "type": "autoupdate"
      },
      {
        "name": "enablelogged",
        "title": "<?php _e('Enable for Logged Users', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('It\'s possible to enable optimization of pages for logged users too. Note that in this case page cache is disabled and other optimizations (HTML, styles, scripts, images) are enabled.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "presets": {
          "ultra": 1,
          "experimental": 1
        }
      },
      {
        "name": "distribmode",
        "global": 1,
        "title": "<?php _e('Distribute Method', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Distribution method of the compressed JS and CSS files to the client device. Different methods perform better on different server setup: \'Direct\' method distributes them in the default method of the webserver (like any other files), but note that gzip compression and caching may be disabled (i.e. those are controlled by the webserver and PSN is not able to affect the settings nor to check is they are currently enabled or not.) \'Apache mod_rewrite + mod_headers\' is the fastest method, but requires Apache with both mod_rewrite and mod_headers modules enabled. \'Apache mod_rewrite\' and \'PHP\' are identical from the performance point of view; the only difference is that \'Apache mod_rewrite\' requires Apache webserver, while \'PHP\' generates not-so-beautiful URLs like /s/get.php?abcdef.js instead of just /s/abcdef.js.', 'psn-pagespeed-ninja'); ?>",
        "type": "select",
        "values": [
          {
            "direct": "<?php _e('Direct', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "apache": "<?php _e('Apache mod_rewrite+mod_headers', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "rewrite": "<?php _e('Apache mod_rewrite', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "php": "<?php _e('PHP', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "direct"
      },
      {
        "name": "staticdir",
        "title": "<?php _e('Optimized Files Directory', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Directory path for the stored combined JS and CSS files (relative to WordPress installation directory).', 'psn-pagespeed-ninja'); ?>",
        "type": "text",
        "default": "/s"
      },
      {
        "name": "cdndomain",
        "title": "<?php _e('CDN Domain', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('The domain name of your CDN server (also referred to as the Pull CDN).', 'psn-pagespeed-ninja'); ?>",
        "type": "text",
        "default": "",
        "pro": 1
      },
      {
        "name": "footer",
        "title": "<?php _e('Support Badge', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Displays a small text link to the PageSpeed Ninja website in the footer (\'Optimized with PageSpeed Ninja\'). A BIG thank you if you use this! :).', 'psn-pagespeed-ninja'); ?>",
        "wizard_description": "<?php _e('Adds a non-obtrusive support badge at the bottom of your website'); ?>",
        "type": "checkbox",
        "default": 1
      },
      {
        "name": "allow_ext_stats",
        "title": "<?php _e('Send Anonymous Statistics', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Send anonymous usage data to PageSpeed Ninja to help us further optimize the plugin for best performance.', 'psn-pagespeed-ninja'); ?>",
        "wizard_description": "<?php _e('Shares non-sensitive, anonymous data, so that we can improve features, fix problems.'); ?>",
        "type": "checkbox",
        "default": 1
      },
      {
        "name": "incompat_audit",
        "title": "<?php _e('Compatibility Audit', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Enable compatibility audit to detect potential issues (incompatibility with other plugins, etc.).', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1
      },
      {
        "name": "dailyrun_time",
        "title": "<?php _e('Daily Run Time', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('The time to daily run scheduled maintenance tasks.', 'psn-pagespeed-ninja'); ?>",
        "type": "time",
        "default": "00:00",
        "pro": 1
      },
      {
        "name": "exec_mode",
        "title": "<?php _e('Execute Commands', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('The method to run CLI tools.', 'psn-pagespeed-ninja'); ?>",
        "type": "select",
        "values": [
          {
            "exec": "<?php _e('exec', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "popen": "<?php _e('popen', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "procopen": "<?php _e('proc_open', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "exec",
        "pro": 1
      },
      {
        "name": "subsection_operation",
        "title": "<?php _e('Operation', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Settings related to optimization mode.', 'psn-pagespeed-ninja'); ?>",
        "type": "subsection"
      },
      {
        "name": "ress_async",
        "global": 1,
        "title": "<?php _e('Operation', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('The type of server-side page optimization: Sync (in-request optimization), AJAX (browser-triggered off-request optimization), and Cron (cron-triggered off-request optimization). Off-request optimization generates page as fast as possible, schedules some heavy optimization tasks, and runs them later.', 'psn-pagespeed-ninja'); ?>",
        "type": "select",
        "values": [
          {
            "sync": "<?php _e('Sync', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "ajax": "<?php _e('AJAX', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "cron": "<?php _e('Cron', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "sync",
        "pro": 1
      },
      {
        "name": "ress_async_max",
        "global": 1,
        "title": "<?php _e('Threads', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Maximal number of optimizing threads.', 'psn-pagespeed-ninja'); ?>",
        "type": "number",
        "default": 1,
        "pro": 1
      },
      {
        "name": "ress_async_maxtime",
        "global": 1,
        "title": "<?php _e('Execution Time', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Execution time limit per thread.', 'psn-pagespeed-ninja'); ?>",
        "type": "number",
        "units": "<?php _e('sec', 'psn-pagespeed-ninja'); ?>",
        "default": 60,
        "pro": 1
      },
      {
        "name": "ress_async_memlimit",
        "global": 1,
        "title": "<?php _e('Memory Limit', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Memory limit per thread (Mb).', 'psn-pagespeed-ninja'); ?>",
        "type": "number",
        "units": "<?php _e('Mb', 'psn-pagespeed-ninja'); ?>",
        "default": 0,
        "pro": 1
      },
      {
        "name": "worker_config_path",
        "global": 1,
        "title": "<?php _e('Path to Master WordPress', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Path to the WordPress installation where the main optimization queue database is stored.', 'psn-pagespeed-ninja'); ?>",
        "type": "text",
        "default": "",
        "pro": 1
      },
      {
        "name": "worker_group",
        "global": 1,
        "title": "<?php _e('User Group', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Change file group of optimized files to this one.', 'psn-pagespeed-ninja'); ?>",
        "type": "text",
        "default": "",
        "pro": 1
      }
    ]
  },
  {
    "title": "<?php _e('Troubleshooting', 'psn-pagespeed-ninja'); ?>",
    "items": [
      {
        "name": "ress_logginglevel",
        "title": "<?php _e('Logging Level', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Specify the degree of detail/verbosity in log messages generated by the optimizing engine.', 'psn-pagespeed-ninja'); ?>",
        "type": "selectlist",
        "values": [
          {
            "0": "<?php _e('None', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "1": "<?php _e('Emergency', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "2": "<?php _e('Alert', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "3": "<?php _e('Critical', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "4": "<?php _e('Error', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "5": "<?php _e('Warning', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "6": "<?php _e('Notice', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "7": "<?php _e('Info', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "8": "<?php _e('Debug', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "5",
        "presets": {
        }
      },
      {
        "name": "errorlogging",
        "title": "<?php _e('PHP Error Logging', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Log all PHP\'s errors, exceptions, warnings, and notices. Please check the content of this file and send it to us if there are messages related to PageSpeed Ninja plugin.', 'psn-pagespeed-ninja'); ?>",
        "type": "errorlogging",
        "default": 0,
        "presets": {
        }
      },
      {
        "global": 2,
        "title": "<?php _e('Images', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Remove optimized images.', 'psn-pagespeed-ninja'); ?>",
        "type": "do_clear_images"
      },
      {
        "global": 2,
        "title": "<?php _e('Downloads', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Remove loaded files.', 'psn-pagespeed-ninja'); ?>",
        "type": "do_clear_loaded",
        "pro": 1
      },
      {
        "global": 2,
        "title": "<?php _e('Optimized Files', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('View size of optimized files (JavaScript, CSS, and other generated files). To remove them, use Cache Clear Expired or Clear All buttons below.', 'psn-pagespeed-ninja'); ?>",
        "type": "do_view_static"
      },
      {
        "global": 2,
        "title": "<?php _e('Cache', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Clear the internal cache files.', 'psn-pagespeed-ninja'); ?>",
        "type": "do_clear_cache"
      },
      {
        "global": 2,
        "title": "<?php _e('Page Cache', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Clear the cache of optimized HTML pages.', 'psn-pagespeed-ninja'); ?>",
        "type": "do_clear_pagecache"
      },
      {
        "name": "subsection_internals",
        "title": "<?php _e('Internals', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "",
        "type": "subsection"
      },
      {
        "name": "htmloptimizer",
        "title": "<?php _e('HTML Parser', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Choose between performance and optimal HTML code: Switch to a new libxml HTML parser or fast page optimizer with full JavaScript, CSS, and images optimization, but with limited subset of HTML optimizations (only supporting the removal of HTML comments and IE conditional comments).', 'psn-pagespeed-ninja'); ?>",
        "type": "select",
        "values": [
          {
            "dom": "<?php _e('Use DOM HTML parser', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "stream": "<?php _e('Use Fast simple HTML parser', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "streamfull": "<?php _e('Use Advanced simple HTML parser', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "pharse": "<?php _e('Use Standard full HTML parser', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "class": "streamoptimizer",
        "default": "dom",
        "presets": {
          "safe": "stream"
        }
      },
      {
        "name": "use_symlink",
        "title": "<?php _e('Use symlinks', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Use symlinks to reduce disk usage.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "safe": 0
        }
      },
      {
        "name": "subsection_html_rules",
        "title": "<?php _e('HTML Exclude Rules', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "",
        "type": "subsection"
      },
      {
        "name": "html_rules_safe_exclude",
        "title": "<?php _e('Bypass', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Exclude matched HTML elements from processing.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": ""
      },
      {
        "name": "subsection_css_rules",
        "title": "<?php _e('CSS Exclude Rules', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "",
        "type": "subsection"
      },
      {
        "name": "css_rules_minify_exclude",
        "title": "<?php _e('Minify Exclude', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Exclude matched styles from minification.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": ""
      },
      {
        "name": "css_rules_merge_bypass",
        "title": "<?php _e('Merge Bypass', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Bypass processing of matched styles.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": ""
      },
      {
        "name": "css_rules_merge_stop",
        "title": "<?php _e('Merge Stop', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Exclude matched styles from merging.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": "onload*="
      },
      {
        "name": "css_rules_merge_exclude",
        "title": "<?php _e('Merge Exclude', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Exclude matched styles from merging.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": "href*=#"
      },
      {
        "name": "css_rules_merge_include",
        "title": "<?php _e('Merge Include', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Allow merging of matched styles.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": ""
      },
      {
        "name": "css_rules_merge_startgroup",
        "title": "<?php _e('Merge Start Group', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Start new merging group on matched styles.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": ""
      },
      {
        "name": "css_rules_merge_stopgroup",
        "title": "<?php _e('Merge Stop Group', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Stop merging group on matched styles.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": ""
      },
      {
        "name": "subsection_js_rules",
        "title": "<?php _e('JavaScript Exclude Rules', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "",
        "type": "subsection"
      },
      {
        "name": "js_rules_minify_exclude",
        "title": "<?php _e('Minify Exclude', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Exclude matched JavaScripts from minification.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": ""
      },
      {
        "name": "js_rules_merge_bypass",
        "title": "<?php _e('Merge Bypass', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Bypass processing of matched JavaScripts.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": ""
      },
      {
        "name": "js_rules_merge_stop",
        "title": "<?php _e('Merge Stop', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Exclude matched JavaScripts from merging.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": "onload*="
      },
      {
        "name": "js_rules_merge_exclude",
        "title": "<?php _e('Merge Exclude', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Exclude matched JavaScripts from merging.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": "src*=#"
      },
      {
        "name": "js_rules_merge_include",
        "title": "<?php _e('Merge Include', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Allow merging of matched JavaScripts.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": ""
      },
      {
        "name": "js_rules_merge_startgroup",
        "title": "<?php _e('Merge Start Group', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Start new merging group on matched JavaScripts.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": ""
      },
      {
        "name": "js_rules_merge_stopgroup",
        "title": "<?php _e('Merge Stop Group', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Stop merging group on matched JavaScripts.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": ""
      },
      {
        "name": "js_rules_move_exclude",
        "title": "<?php _e('Move Exclude', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Disallow moving of matched JavaScripts.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": ""
      },
      {
        "name": "js_rules_async_exclude",
        "title": "<?php _e('Async Exclude', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Exclude matched JavaScripts from auto-async.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": "~=\\.write(?:ln)?\\(\\s*[^)]"
      },
      {
        "name": "js_rules_async_include",
        "title": "<?php _e('Async Include', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Set matched JavaScripts as async.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": ""
      },
      {
        "name": "js_rules_defer_exclude",
        "title": "<?php _e('Defer Exclude', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Exclude matched JavaScripts from auto-defer.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": "~=\\.write(?:ln)?\\(\\s*[^)]"
      },
      {
        "name": "js_rules_defer_include",
        "title": "<?php _e('Defer Include', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Set matched JavaScripts as deferred.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": ""
      },
      {
        "name": "subsection_img_rules",
        "title": "<?php _e('Image Exclude Rules', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "",
        "type": "subsection"
      },
      {
        "name": "img_rules_minify_exclude",
        "title": "<?php _e('Minify Exclude', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Exclude matched images from optimization.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": ""
      },
      {
        "name": "subsection_lazyload_rules",
        "title": "<?php _e('Lazyload Exclude Rules', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "",
        "type": "subsection"
      },
      {
        "name": "lazyload_rules_img_exclude",
        "title": "<?php _e('Image Exclude', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Exclude matched images from lazy loading.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": ""
      },
      {
        "name": "lazyload_rules_img_bg_exclude",
        "title": "<?php _e('Background Image Exclude', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Exclude matched nodes from lazy loading of background images.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": "",
        "pro": 1
      },
      {
        "name": "lazyload_rules_video_exclude",
        "title": "<?php _e('Video Exclude', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Exclude matched videos from lazy loading.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": "class*=wp-video-shortcode"
      },
      {
        "name": "lazyload_rules_iframe_exclude",
        "title": "<?php _e('Iframe Exclude', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Exclude matched iframes from lazy loading.', 'psn-pagespeed-ninja'); ?>",
        "type": "rules",
        "default": ""
      }
    ]
  },
  {
    "id": "server-response-time",
    "title": "<?php _e('Initial server response time was short', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "caching",
        "global": 1,
        "title": "<?php _e('Caching', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Enable server-side page caching.', 'psn-pagespeed-ninja'); ?>",
        "type": "cachingcheckbox",
        "default": 1,
        "presets": {
          "compact": 0
        }
      },
      {
        "name": "caching_driver",
        "global": 1,
        "title": "<?php _e('Cache Storage', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Select the preferred storage method for cached data to optimize performance (file system, Redis server).', 'psn-pagespeed-ninja'); ?>",
        "type": "cachingdriver",
        "values": [
          {
            "file": "<?php _e('File system', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "$redis": "<?php _e('Redis', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "file",
        "pro": 1
      },
      {
        "name": "redis_uri",
        "global": 1,
        "title": "<?php _e('Redis Server', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Specify the address and port for connecting to the Redis server, separated by a colon.', 'psn-pagespeed-ninja'); ?>",
        "type": "text",
        "default": "localhost:6379",
        "pro": 1
      },
      {
        "name": "caching_fast",
        "global": 1,
        "title": "<?php _e('Fast Caching', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Enable fast server-side page caching.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "pro": 1,
        "presets": {
          "safe": 0
        }
      },
      {
        "name": "caching_processed",
        "title": "<?php _e('Experimental Caching', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Extra caching level for optimized pages (may require more disk space).', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "presets": {
          "ultra": 1,
          "experimental": 1
        }
      },
      {
        "name": "caching_ttl",
        "global": 1,
        "title": "<?php _e('Caching Time-to-live', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Caching time-to-live in minutes. Cached data will be invalidated after specified time interval. PageSpeed Ninja automatically invalidates cache when a new comment is added, a new post is published, theme is changed, etc. (I.e., this affects how frequently comments should be updated for unauthorized users.) 15 minutes is a reasonable time in most cases, but if commenting is disabled, this could be increased to one day (1440 mins).', 'psn-pagespeed-ninja'); ?>",
        "type": "number",
        "units": "<?php _e('min', 'psn-pagespeed-ninja'); ?>",
        "default": 1440,
        "presets": {
          "safe": 15,
          "ultra": 10080,
          "experimental": 10080
        }
      },
      {
        "name": "pagecache_devicedependent",
        "title": "<?php _e('Mobile Cache', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('The Mobile Cache setting is used to separate mobile and desktop page caches on the server. Use this setting if you have an adaptive design approach (i.e. different content for mobile and desktop browsers).', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": "0",
        "pro": 1
      },
      {
        "name": "pagecache_search",
        "title": "<?php _e('Cache Search Queries', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Cache pages with search queries results (may result in larger cache size).', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": "0"
      },
      {
        "name": "pagecache_autowarm",
        "title": "<?php _e('Auto-warm Cache', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Update Page Cache daily.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": "0",
        "pro": 1
      },
      {
        "name": "pagecache_autowarm_urls",
        "title": "<?php _e('Auto-warm URLs', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('One-per-line list of URLs to auto-warm (relative to the website\'s root, e.g. /, /login, etc.).', 'psn-pagespeed-ninja'); ?>",
        "type": "textarea",
        "default": "",
        "pro": 1
      },
      {
        "name": "pagecache_disable_queries",
        "title": "<?php _e('Disable for URLs with Query', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Disable Page Cache for URL with query parameters (e.g. search requests).', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": "0"
      },
      {
        "name": "pagecache_params_skip",
        "title": "<?php _e('Skip Queries', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('These query parameters does not affect the page content.', 'psn-pagespeed-ninja'); ?>",
        "type": "textarea",
        "default": "utm_source\nutm_medium\nutm_campaign\nutm_content\nutm_term\ngclid\nfbclid\ndclid\nyclid"
      },
      {
        "name": "pagecache_exclude_urls",
        "title": "<?php _e('Exclude Pages', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Disable Page Cache for specified URLs.', 'psn-pagespeed-ninja'); ?>",
        "type": "textarea",
        "default": "/cart\n/checkout"
      },
      {
        "name": "pagecache_cookies_disable",
        "title": "<?php _e('Disable for Cookies', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Disable Page Cache for visitors with any of the specified cookie set.', 'psn-pagespeed-ninja'); ?>",
        "type": "textarea",
        "default": "woocommerce_items_in_cart"
      },
      {
        "name": "pagecache_cookies_depend",
        "title": "<?php _e('Depend on Cookies', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Cache page depending on values of the specified cookies.', 'psn-pagespeed-ninja'); ?>",
        "type": "textarea",
        "default": "wp_lang\npsn_visitor"
      },
      {
        "name": "pagecache_http_depend",
        "title": "<?php _e('Depend on HTTP Headers', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Cache page depending on values of the specified HTTP headers.', 'psn-pagespeed-ninja'); ?>",
        "type": "textarea",
        "default": ""
      }
    ]
  },
  {
    "id": "uses-long-cache-ttl",
    "title": "<?php _e('Serve static assets with an efficient cache policy', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "htaccess_caching",
        "global": 1,
        "title": "<?php _e('Caching in .htaccess', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Update .htaccess files in wp-includes, wp-content, and uploads directories for better front-end performance (for Apache webserver).', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": "auto",
        "presets": {
          "safe": 0,
          "compact": 1,
          "optimal": 1,
          "ultra": 1,
          "experimental": 1
        }
      },
      {
        "name": "css_loadurl",
        "title": "<?php _e('Load External Stylesheets', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Load external files for optimization and merging. Disable if you use external dynamically generated CSS files.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "pro": 1,
        "presets": {
          "safe": 0,
          "compact": 0
        }
      },
      {
        "name": "js_loadurl",
        "title": "<?php _e('Load External Scripts', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Load external files for optimization and merging. Disable if you use external dynamically generated JavaScript files.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "pro": 1,
        "presets": {
          "safe": 0,
          "compact": 0
        }
      },
      {
        "name": "img_loadurl",
        "title": "<?php _e('Load External Images', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Load external files for optimization. Disable if you use external dynamically generated image files.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "pro": 1,
        "presets": {
          "safe": 0,
          "compact": 0
        }
      },
      {
        "name": "font_loadurl",
        "title": "<?php _e('Load External Fonts', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Load external files for optimization. Disable if you use external dynamically generated font files.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "pro": 1,
        "presets": {
          "safe": 0,
          "compact": 0
        }
      },
      {
        "name": "load_google_analytics",
        "title": "<?php _e('Load Google Analytics', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Replace Google Analytics by loaded local copy.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "pro": 1,
        "presets": {
          "ultra": 1,
          "experimental": 1
        }
      },
      {
        "name": "load_disable_queries",
        "title": "<?php _e('Exclude Queries', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Don\'t load resources containing query parameters.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "pro": 1
      },
      {
        "name": "load_disable_php",
        "title": "<?php _e('Exclude PHP Scripts', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Don\'t load resources generated by PHP scripts.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "pro": 1
      },
      {
        "name": "load_allowed_domains",
        "title": "<?php _e('Allowed Domains', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Allow loading of external resources from this list of domains only.', 'psn-pagespeed-ninja'); ?>",
        "type": "textarea",
        "default": "ajax.aspnetcdn.com\ncdn.bootcss.com\nmaxcdn.bootstrapcdn.com\ncdnjs.cloudflare.com\nssl.google-analytics.com\nwww.google-analytics.com\najax.googleapis.com\n0.gravatar.com\n1.gravatar.com\n2.gravatar.com\n3.gravatar.com\nwww.gravatar.com\ncode.ionicframework.com\ncdn.jsdelivr.net\ncode.jquery.com\ncdn.jquerytools.org\ncdn.materialdesignicons.com\noss.maxcdn.com\ntwemoji.maxcdn.com\najax.microsoft.com\ncdn.optimizely.com\nwww.parsecdn.com\nrawgit.com\ncdn.rawgit.com",
        "pro": 1
      },
      {
        "name": "load_method",
        "title": "<?php _e('Loading Method', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Method to download resources: PHP stream (via file_get_contents), cURL (via curl extension), or sock (via fsockopen function).', 'psn-pagespeed-ninja'); ?>",
        "type": "select",
        "values": [
          {
            "stream": "<?php _e('PHP stream', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "curl": "<?php _e('cURL', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "sock": "<?php _e('sock', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "stream",
        "pro": 1
      },
      {
        "name": "load_timeout",
        "title": "<?php _e('Loading Timeout', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Break loading if remote server doesn\'t response in specified time.', 'psn-pagespeed-ninja'); ?>",
        "type": "number",
        "units": "<?php _e('sec', 'psn-pagespeed-ninja'); ?>",
        "default": 5,
        "pro": 1
      },
      {
        "name": "load_user_agent",
        "title": "<?php _e('User-Agent', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Use this User-Agent header for resource loading.', 'psn-pagespeed-ninja'); ?>",
        "type": "text",
        "default": "",
        "pro": 1
      }
    ]
  },
  {
    "id": "uses-text-compression",
    "title": "<?php _e('Enable text compression', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "html_gzip",
        "title": "<?php _e('Gzip Compression', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Compress pages using Gzip for better performance. Recommended.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "safe": 0
        }
      },
      {
        "name": "htaccess_gzip",
        "global": 1,
        "title": "<?php _e('Gzip Compression in .htaccess', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Update .htaccess files in wp-includes, wp-content, and uploads directories for better front-end performance (for Apache webserver).', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "presets": {
          "compact": 1,
          "optimal": 1,
          "ultra": 1,
          "experimental": 1
        }
      },
      {
        "name": "html_sortattr",
        "title": "<?php _e('Reorder Attributes', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Reorder HTML attributes for better gzip compression. Recommended. Disable if there is a conflict with another extension that rely on an exact HTML attribute order.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "class": "streamdisabled",
        "default": 0,
        "presets": {
          "ultra": 1,
          "experimental": 1
        }
      }
    ]
  },
  {
    "id": "uses-rel-preconnect",
    "title": "<?php _e('Preconnect to required origins', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "dnsprefetch",
        "title": "<?php _e('DNS Prefetch', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Use DNS pre-fetching for external domain names. Disable if there is another plugin doing the same thing and there is a conflict with PageSpeed Ninja.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "safe": 0
        }
      },
      {
        "title": "<?php _e('DNS Prefetch Assistant', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Semi-automatical assistant for choosing of domains to prefetch.', 'psn-pagespeed-ninja'); ?>",
        "type": "do_dnsprefetch_assistant",
        "pro": 1
      },
      {
        "name": "dnsprefetch_domain",
        "title": "<?php _e('Domains', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('List of Domains for DNS prefetch.', 'psn-pagespeed-ninja'); ?>",
        "type": "textarea",
        "default": ""
      }
    ]
  },
  {
    "id": "uses-rel-preload",
    "title": "<?php _e('Preload key requests', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "title": "<?php _e('Preload Assistant', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Semi-automatical assistant for choosing of files to preload.', 'psn-pagespeed-ninja'); ?>",
        "type": "do_preload_assistant",
        "pro": 1
      },
      {
        "name": "preload_style",
        "title": "<?php _e('CSS Styles', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('List of stylesheet files to preload.', 'psn-pagespeed-ninja'); ?>",
        "type": "textarea",
        "default": ""
      },
      {
        "name": "preload_font",
        "title": "<?php _e('Fonts', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('List of font files to preload.', 'psn-pagespeed-ninja'); ?>",
        "type": "textarea",
        "default": ""
      },
      {
        "name": "preload_script",
        "title": "<?php _e('JavaScripts', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('List of JavaScript files to preload.', 'psn-pagespeed-ninja'); ?>",
        "type": "textarea",
        "default": ""
      },
      {
        "name": "preload_module",
        "title": "<?php _e('JavaScript Modules', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('List of JavaScript module files to preload.', 'psn-pagespeed-ninja'); ?>",
        "type": "textarea",
        "default": ""
      },
      {
        "name": "preload_image",
        "title": "<?php _e('Images', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('List of image files to preload.', 'psn-pagespeed-ninja'); ?>",
        "type": "textarea",
        "default": ""
      }
    ]
  },
  {
    "id": "unminified-css",
    "title": "<?php _e('Minify CSS', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "css_di_cssMinify",
        "title": "<?php _e('Minify CSS Method', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Optimizes CSS for better performance. This optimizes CSS correspondingly (removes unnecessary whitespaces, unused code etc.). If there are any CSS issues, disable the minification (and wait for a plugin update).', 'psn-pagespeed-ninja'); ?>",
        "type": "select",
        "values": [
          {
            "none": "<?php _e('None', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "ress": "<?php _e('PageSpeed Ninja', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "$exec": "<?php _e('Command', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "ress",
        "presets": {
          "safe": "none"
        }
      },
      {
        "name": "css_minify_exec",
        "title": "<?php _e('Command', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('External (CLI) command to optimize CSS styles.', 'psn-pagespeed-ninja'); ?>",
        "type": "text",
        "default": "csso {{TARGET}} -o {{TARGET}}",
        "pro": 1
      },
      {
        "name": "css_minifyattribute",
        "title": "<?php _e('Minify Style Attributes', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Optimizes CSS styles within \'style\' attributes. (Usually these attributes are short, and as such have insignificant effect on the HTML size, however the processing takes time and that may affect the total page generation time.)', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "presets": {
          "ultra": 1,
          "experimental": 1
        }
      },
      {
        "name": "css_crossfileoptimization",
        "title": "<?php _e('Cross-files Optimization', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Optimize the generated combined CSS file.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "presets": {
          "ultra": 1,
          "experimental": 1
        }
      }
    ]
  },
  {
    "id": "unused-css-rules",
    "title": "<?php _e('Reduce unused CSS', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
    ]
  },
  {
    "id": "unminified-javascript",
    "title": "<?php _e('Minify JavaScript', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "js_di_jsMinify",
        "title": "<?php _e('Minify JavaScript Method', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Optimizes JavaScript for better performance. This optimizes JavaScript correspondingly (removes unnecessary whitespaces, unused code etc.).', 'psn-pagespeed-ninja'); ?>",
        "type": "select",
        "values": [
          {
            "none": "<?php _e('None', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "jsmin": "<?php _e('JsMin', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "$exec": "<?php _e('Command', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "none",
        "presets": {
          "ultra": "jsmin",
          "experimental": "jsmin"
        }
      },
      {
        "name": "js_minify_exec",
        "title": "<?php _e('Command', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('External (CLI) command to optimize JavaScript styles.', 'psn-pagespeed-ninja'); ?>",
        "type": "text",
        "default": "uglifyjs {{TARGET}} -o {{TARGET}}",
        "pro": 1
      },
      {
        "name": "js_minifyattribute",
        "title": "<?php _e('Minify Event Attributes', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Optimizes JavaScript in event attributes (e.g. \'onclick\' or \'onsubmit\').', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "class": "streamdisabled",
        "default": 0,
        "presets": {
          "ultra": 1,
          "experimental": 1
        }
      },
      {
        "name": "js_crossfileoptimization",
        "title": "<?php _e('Cross-files Optimization', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Optimize the generated combined JavaScript file. This option doubles the JavaScript optimization time (though the good news is that it is done only once) and should be enabled only if you really want to get the JS size down to as small as possible.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "presets": {
          "ultra": 1,
          "experimental": 1
        }
      }
    ]
  },
  {
    "id": "render-blocking-resources",
    "title": "<?php _e('Eliminate render-blocking resources', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "css_abovethefold",
        "title": "<?php _e('Critical CSS', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Use auto-generated critical (above-the-fold) CSS styles. Disable it if the above-the-fold CSS is generated incorrectly, or the page is rendered with the aid of a lot of JavaScript and above-the-fold CSS has no effect on the rendering.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "safe": 0
        }
      },
      {
        "name": "css_abovethefoldglobal",
        "title": "<?php _e('Use Globally', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Use critical CSS styles on home page only, or on every page (globally).', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0
      },
      {
        "name": "allow_ext_atfcss",
        "title": "<?php _e('Remote Critical CSS Generation', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Allow the use of PageSpeed.Ninja critical CSS generation service on the PageSpeed Ninja server. When this setting is disabled, this plugin contains a simplified version of the generation tool that works directly in the browser, but using it requires you to manually visit the PageSpeed settings page to regenerate the critical CSS after each change to the website. Enabling this setting allows the use of the PageSpeed Ninja server to have the critical CSS regenerated automatically.', 'psn-pagespeed-ninja'); ?>",
        "wizard_description": "<?php _e('Generates critical CSS automatically, rather than you doing it manually.'); ?>",
        "type": "checkbox",
        "default": 1
      },
      {
        "name": "css_abovethefoldcookie",
        "title": "<?php _e('Critical CSS Cookie', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Use a cookie to embed critical CSS styles for first-time visitors only. Using this cookie allows not sending the critical CSS with every request (as all necessary CSS files will be cached by the browser), but the setting may be disabled if PageSpeed Ninja is used with a 3rd party caching plugin.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
        }
      },
      {
        "name": "css_abovethefoldlocal",
        "title": "<?php _e('Local Critical CSS Generation', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Critical CSS styles may be generated either locally (directly in your browser), or externally using PageSpeed Ninja\'s service. \'Local\' uses the current browser to generate the CSS (in some cases the result may be different depending on browser: Chrome-based ones are recommended), \'External\' uses PageSpeed Ninja\'s unique service with extra improvements and minification.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "presets": {
        }
      },
      {
        "name": "css_abovethefoldstyle",
        "title": "<?php _e('Critical CSS Styles', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Critical (above-the-fold) CSS styles. It is generated automatically, but you may insert custom styling or edit the auto-generated version below.', 'psn-pagespeed-ninja'); ?>",
        "type": "abovethefoldstyle",
        "default": ""
      },
      {
        "name": "css_abovethefoldautoupdate",
        "title": "<?php _e('Auto Update Critical CSS', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Update critical CSS styles daily.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "pro": 1,
        "presets": {
        }
      },
      {
        "name": "css_inlinefirsttime",
        "title": "<?php _e('Inline CSS for First-Time Visitors', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Embed CSS for faster initial page loading.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "presets": {
         "experimental": 1
        }
      },
      {
        "name": "js_inlinefirsttime",
        "title": "<?php _e('Inline JavaScript for First-Time Visitors', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Embed JavaScript for faster initial page loading.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "presets": {
        "experimental": 1
        }
      },
      {
        "name": "js_forcedefer",
        "title": "<?php _e('Force defer', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Use deferred loading (via \'defer\' attribute) for all JavaScripts.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "safe": 0
        }
      },
      {
        "name": "js_forceasync",
        "title": "<?php _e('Force async', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Use asynchronous loading (via \'async\' attribute) for all JavaScripts.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0
      },
      {
        "name": "css_nonblockjs",
        "title": "<?php _e('Non-blocking JavaScript', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Load JavaScript asynchronously with a few seconds\' delay after the webpage is displayed in the browser. This speeds up the page rendering by defrering the loading of all JS. May significantly improve the loading time (and the PageSpeed Insight score), but leads to a flash of unstyled text, may affect stats in Google Analytics, and some other side effects.', 'psn-pagespeed-ninja'); ?>",
        "experimental": 1,
        "type": "checkbox",
        "default": 0,
        "presets": {
          "ultra": 1,
          "experimental": 1
        }
      }
    ]
  },
  {
    "id": "font-display",
    "title": "<?php _e('Ensure text remains visible during webfont load', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "css_googlefonts",
        "title": "<?php _e('Google Fonts Loading', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Used to optimize the loading of Google Fonts. Auto: browser-defined strategy. Block: uses invisible font for 3s, then fallback font (Flash of Invisible Text). Swap: invisible font for 0.1s, then fallback (Flash of Unstyled Text). Fallback: invisible font for 0.1s, then fallback, no switch to custom font after 3s. Optional: invisible font for 0.1s, then no switch to custom font. None: disable optimization.', 'psn-pagespeed-ninja'); ?>",
        "type": "select",
        "values": [
          {
            "": "<?php _e('None', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "auto": "<?php _e('Auto', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "block": "<?php _e('Block', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "swap": "<?php _e('Swap', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "fallback": "<?php _e('Fallback', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "optional": "<?php _e('Optional', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "swap",
        "presets": {
          "experimental": "optional"
        }
      },
      {
        "name": "css_googlefont_async",
        "title": "<?php _e('Async Google Fonts', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Asynchronous loading of Google Fonts.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "safe": 0
        },
        "pro": 1
      },
      {
        "name": "css_fontdisplay",
        "title": "<?php _e('Display Web-fonts', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Used to optimize the loading of Web Fonts. \'Block\': uses invisible font for 3s, then fallback font (Flash of Invisible Text). \'Swap\': invisible font for 0.1s, then fallback (Flash of Unstyled Text). \'Fallback\': invisible font for 0.1s, then fallback, no switch to custom font after 3s. \'Optional\': invisible font for 0.1s, then no switch to custom font. \'None\': disable optimization.', 'psn-pagespeed-ninja'); ?>",
        "type": "select",
        "values": [
          {
            "": "<?php _e('None', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "block": "<?php _e('Block', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "swap": "<?php _e('Swap', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "fallback": "<?php _e('Fallback', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "optional": "<?php _e('Optional', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "swap",
        "presets": {
          "safe": "",
          "experimental": "optional"
        }
      },
      {
        "name": "css_fontdisplayswap_exclude",
        "title": "<?php _e('Exclude Web-fonts', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Do not affect loading of the following Web fonts.', 'psn-pagespeed-ninja'); ?>",
        "type": "textarea",
        "default": "FontAwesome\nFont Awesome"
      }
    ]
  },
  {
    "id": "redirects",
    "title": "<?php _e('Avoid multiple page redirects', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
    ]
  },
  {
    "id": "total-byte-weight",
    "title": "<?php _e('Avoids enormous network payloads', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "subsection_html",
        "title": "<?php _e('HTML', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Settings related to HTML optimization.', 'psn-pagespeed-ninja'); ?>",
        "type": "subsection"
      },
      {
        "name": "html_mergespace",
        "title": "<?php _e('Merge Whitespaces', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Removes empty spaces from the HTML code for faster loading. Recommended. Disable if there is a conflict with the rule \'white-space: pre\' in CSS. (This is rarely needed, as usually the &lt;pre&gt; element is used for this behaviour, and PSN processes &lt;pre&gt; correctly by keeping all spaces in place.)', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "safe": 0
        }
      },
      {
        "name": "html_removecomments",
        "title": "<?php _e('Remove Comments', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Removes comments from the HTML code for faster loading. Disable if there is a conflict with another plugin (e.g. a plugin which uses JavaScript to extract content of comments).', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
        }
      },
      {
        "name": "html_minifyurl",
        "title": "<?php _e('Minify URLs', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Replaces absolute URLs (http://www.website.com/link) with relative URLs (/link) to reduce page size. Disable if there is a conflict with another plugin (e.g. plugin which uses JavaScript that depends on having the full URL in certain href attributes.).', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
        }
      },
      {
        "name": "html_removedefattr",
        "title": "<?php _e('Remove Default Attributes', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Remove attributes with default values, e.g. type=\'text\' in &lt;input&gt; tag. It reduces total page size. Disable in the case of conflicts with CSS (e.g. \'input[type=text]\' selector doesn\'t match \'input\' element without \'type\' attribute).', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "class": "streamdisabled",
        "default": 0,
        "presets": {
          "ultra": 1,
          "experimental": 1
        }
      },
      {
        "name": "html_removeiecond",
        "title": "<?php _e('Remove IE Conditionals', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Remove IE conditional commenting tags for non-IE browsers. Disable if there is a conflict with another plugin that relies on these tags.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "presets": {
          "ultra": 1,
          "experimental": 1
        }
      },
      {
        "name": "subsection_css",
        "title": "<?php _e('CSS', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Settings related to CSS optimization.', 'psn-pagespeed-ninja'); ?>",
        "type": "subsection"
      },
      {
        "name": "css_merge",
        "title": "<?php _e('Merge CSS', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Merge several CSS files into single one for faster loading. Disable different pages load different CSS files.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
        }
      },
      {
        "name": "css_mergeinline",
        "title": "<?php _e('Merge Embedded Styles', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Merge embedded CSS styles in &lt;style&gt;...&lt;/style&gt; blocks. Disable for dynamically-generated embedded CSS styles - though if the dynamic CSS is the same on all pages, this feature may be kept enabled. But if different pages have different embedded CSS, this feature should be disabled.', 'psn-pagespeed-ninja'); ?>",
        "type": "select",
        "values": [
          {
            "0": "<?php _e('Disable', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "head": "<?php _e('In &lt;head&gt; only', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "1": "<?php _e('Everywhere', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "head",
        "presets": {
          "safe": "0",
          "compact": "0",
          "ultra": "1",
          "experimental": "1"
        }
      },
      {
        "name": "css_inlinelimit",
        "title": "<?php _e('Inline Limit', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Inline limit allows to inline small CSS (up to the specified limit) into the page directly in order to avoid sending additional requests to the server (i.e. speeds up loading). 1024 bytes is likely optimal for most cases, allowing inlining of small files while not inlining large ones.', 'psn-pagespeed-ninja'); ?>",
        "type": "number",
        "units": "<?php _e('bytes', 'psn-pagespeed-ninja'); ?>",
        "default": 4096,
        "presets": {
        }
      },
      {
        "name": "css_checklinkattributes",
        "title": "<?php _e('Keep Extra link Tag Attributes', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Don\'t merge a stylesheet if its \'link\' tag contains extra attribute(s) (e.g. \'id\', in rare cases it might mean that JavaScript code may refer to this stylesheet HTML node).', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "presets": {
          "safe": 1
        }
      },
      {
        "name": "css_checkstyleattributes",
        "title": "<?php _e('Keep Extra style Tag Attributes', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Don\'t merge a stylesheet if its \'style\' tag contains extra attribute(s) (e.g. \'id\', in rare cases it might mean that javascript code may refer to this stylesheet HTML node)', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "presets": {
          "safe": 1,
          "compact": 1,
          "optimal": 1
        }
      },
      {
        "name": "subsection_js",
        "title": "<?php _e('JavaScript', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Settings related to JavaScript optimization.', 'psn-pagespeed-ninja'); ?>",
        "type": "subsection"
      },
      {
        "name": "js_merge",
        "title": "<?php _e('Merge Scripts', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Merge several JavaScript files into a single one for faster loading. Recommended.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "ultra": 0
        }
      },
      {
        "name": "js_mergeinline",
        "title": "<?php _e('Merge Embedded Scripts', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Merge embedded JavaScript code in &lt;script&gt;...&lt;/script&gt; code blocks. Disable for dynamically-generated embedded JavaScript code.', 'psn-pagespeed-ninja'); ?>",
        "type": "select",
        "values": [
          {
            "0": "<?php _e('Disable', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "head": "<?php _e('In &lt;head&gt; only', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "1": "<?php _e('Everywhere', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "head",
        "presets": {
          "safe": "0",
          "compact": "0",
          "ultra": "0",
          "experimental": "1"
        }
      },
      {
        "name": "js_automove",
        "title": "<?php _e('Auto Move', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Allows to relocate script tags for more effienct merging. Blocking scripts generates \'inplace\' HTML content and in general should not be relocated. Disable if you use blocking scripts, e.g. synchronous Google Adsense ad code.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "safe": 0
        }
      },
      {
        "name": "js_skipinits",
        "title": "<?php _e('Skip Initialization Scripts', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Allows to skip short inlined initialization-like scripts (e.g. &lt;script&gt;var x=&quot;zzz&quot;&lt;/script&gt;) from merging and optimization.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "safe": 0
        }
      },
      {
        "name": "js_inlinelimit",
        "title": "<?php _e('Inline Limit', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Inline limit allows to inline small JavaScript (up to the specified limit) into the page directly in order to avoid sending additional requests to the server (i.e. speeds up loading)1024 bytes is likely optimal for most cases, allowing inlining of small JavaScript files while not inlining large files like jQuery.', 'psn-pagespeed-ninja'); ?>",
        "type": "number",
        "units": "<?php _e('bytes', 'psn-pagespeed-ninja'); ?>",
        "default": 4096,
        "presets": {
        }
      },
      {
        "name": "js_checkattributes",
        "title": "<?php _e('Keep Extra script Tag Attributes', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Don\'t merge JavaScript if its \'script\' tag contains extra attributes (e.g. \'id\', in rare cases it might mean that JavaScript code may refer to this stylesheet HTML node).', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "presets": {
          "safe": 1
        }
      },
      {
        "name": "js_wraptrycatch",
        "title": "<?php _e('Wrap to try/catch', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Browsers stop the execution of JavaScript code if a parsing or execution error is found, meaning that merged JavaScript files may be stopped in the case of an error in one of the source files. This option enables the wrapping of each merged JavaScript files into a try/catch block to continue the execution after a possible error, but note that enabling this may reduce the performance in some browsers.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
        }
      },
      {
        "name": "wp_mergewpemoji",
        "title": "<?php _e('Optimize Emoji Loading', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Change the way the WP Emoji script is loaded.', 'psn-pagespeed-ninja'); ?>",
        "type": "select",
        "values": [
          {
            "default": "<?php _e('Default Wordpress behaviour', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "merge": "<?php _e('Merge with other scripts', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "disable": "<?php _e('Don\'t load', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "merge",
        "presets": {
        }
      }
    ]
  },
  {
    "id": "uses-optimized-images",
    "title": "<?php _e('Efficiently encode images', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "img_minify",
        "title": "<?php _e('Optimization', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Reduce the size of the images for faster loading and less bandwidth needed using the selected rescaling quality.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "safe": 0,
          "compact": 0
        }
      },
      {
        "name": "img_driver",
        "title": "<?php _e('Default Images Handler', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Method to deal with images. By default PHP supports GD2 only, but may be configured to support ImageMagick API as well.', 'psn-pagespeed-ninja'); ?>",
        "type": "imgdriver",
        "values": [
          {
            "gd2": "<?php _e('GD2', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "imagick": "<?php _e('ImageMagick', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "gd2",
        "pro": 1
      },
      {
        "name": "img_jpegquality",
        "title": "<?php _e('JPEG Quality', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('You can set the image rescaling quality between 0 (low) and 100 (high). Higher means better quality. The recommended level is 75%-95%.', 'psn-pagespeed-ninja'); ?>",
        "type": "number",
        "units": "<?php _e('%', 'psn-pagespeed-ninja'); ?>",
        "default": 85,
        "presets": {
          "safe": 95
        }
      },
      {
        "name": "img_driver_jpeg",
        "title": "<?php _e('JPEG Handler', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Method to deal with images: None (don\'t optimize), GD2 (built-in PHP library), ImageMagick (additional PHP library), or Command (external CLI tool).', 'psn-pagespeed-ninja'); ?>",
        "type": "imgdriver",
        "values": [
          {
            "": "<?php _e('Default', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "none": "<?php _e('None', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "gd2": "<?php _e('GD2', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "imagick": "<?php _e('ImageMagick', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "exec": "<?php _e('Command', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "",
        "pro": 1
      },
      {
        "name": "img_exec_jpeg",
        "title": "<?php _e('JPEG Command', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('External (CLI) command to optimize JPEG images.', 'psn-pagespeed-ninja'); ?>",
        "type": "text",
        "default": "jpegoptim -f --strip-all --all-progressive --max={{Q}} {{TARGET}}",
        "pro": 1
      },
      {
        "name": "img_webpquality",
        "title": "<?php _e('WebP Quality', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('You can set the image rescaling quality between 0 (low) and 100 (high). Higher means better quality. The recommended level is 75%-95%.', 'psn-pagespeed-ninja'); ?>",
        "type": "number",
        "units": "<?php _e('%', 'psn-pagespeed-ninja'); ?>",
        "default": 85,
        "presets": {
          "safe": 95
        }
      },
      {
        "name": "img_driver_webp",
        "title": "<?php _e('WebP Handler', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Method to deal with images: None (don\'t optimize), GD2 (built-in PHP library), ImageMagick (additional PHP library), or Command (external CLI tool).', 'psn-pagespeed-ninja'); ?>",
        "type": "imgdriver",
        "values": [
          {
            "": "<?php _e('Default', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "none": "<?php _e('None', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "gd2": "<?php _e('GD2', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "imagick": "<?php _e('ImageMagick', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "exec": "<?php _e('Command', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "",
        "pro": 1
      },
      {
        "name": "img_exec_webp",
        "title": "<?php _e('WebP Command', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('External (CLI) command to optimize WebP images.', 'psn-pagespeed-ninja'); ?>",
        "type": "text",
        "default": "",
        "pro": 1
      },
      {
        "name": "img_avifquality",
        "title": "<?php _e('AVIF Quality', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('You can set the image rescaling quality between 0 (low) and 100 (high). Higher means better quality. The recommended level is 60%-90%.', 'psn-pagespeed-ninja'); ?>",
        "type": "number",
        "units": "<?php _e('%', 'psn-pagespeed-ninja'); ?>",
        "default": 70,
        "presets": {
          "safe": 90
        }
      },
      {
        "name": "img_driver_avif",
        "title": "<?php _e('AVIF Handler', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Method to deal with images: None (don\'t optimize), GD2 (built-in PHP library), ImageMagick (additional PHP library), or Command (external CLI tool).', 'psn-pagespeed-ninja'); ?>",
        "type": "imgdriver",
        "values": [
          {
            "": "<?php _e('Default', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "none": "<?php _e('None', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "gd2": "<?php _e('GD2', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "imagick": "<?php _e('ImageMagick', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "exec": "<?php _e('Command', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "",
        "pro": 1
      },
      {
        "name": "img_exec_avif",
        "title": "<?php _e('AVIF Command', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('External (CLI) command to optimize AVIF images.', 'psn-pagespeed-ninja'); ?>",
        "type": "text",
        "default": "",
        "pro": 1
      },
      {
        "name": "img_driver_png",
        "title": "<?php _e('PNG Handler', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Method to deal with images: None (don\'t optimize), GD2 (built-in PHP library), ImageMagick (additional PHP library), or Command (external CLI tool).', 'psn-pagespeed-ninja'); ?>",
        "type": "imgdriver",
        "values": [
          {
            "": "<?php _e('Default', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "none": "<?php _e('None', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "gd2": "<?php _e('GD2', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "imagick": "<?php _e('ImageMagick', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "exec": "<?php _e('Command', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "",
        "pro": 1
      },
      {
        "name": "img_exec_png",
        "title": "<?php _e('PNG Command', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('External (CLI) command to optimize PNG images.', 'psn-pagespeed-ninja'); ?>",
        "type": "text",
        "default": "optipng -o7 {{TARGET}}",
        "pro": 1
      },
      {
        "name": "img_driver_gif",
        "title": "<?php _e('GIF Handler', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Method to deal with images: None (don\'t optimize), GD2 (built-in PHP library), ImageMagick (additional PHP library), or Command (external CLI tool).', 'psn-pagespeed-ninja'); ?>",
        "type": "imgdriver",
        "values": [
          {
            "": "<?php _e('Default', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "none": "<?php _e('None', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "gd2": "<?php _e('GD2', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "imagick": "<?php _e('ImageMagick', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "exec": "<?php _e('Command', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "",
        "pro": 1
      },
      {
        "name": "img_exec_gif",
        "title": "<?php _e('GIF Command', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('External (CLI) command to optimize GIF images.', 'psn-pagespeed-ninja'); ?>",
        "type": "text",
        "default": "gifsicle --batch {{TARGET}}",
        "pro": 1
      },
      {
        "name": "img_driver_svg",
        "title": "<?php _e('SVG Handler', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Method to deal with images: None (don\'t optimize), GD2 (built-in PHP library), ImageMagick (additional PHP library), or Command (external CLI tool).', 'psn-pagespeed-ninja'); ?>",
        "type": "imgdriver",
        "values": [
          {
            "none": "<?php _e('None', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "gzip": "<?php _e('Gzip', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "exec": "<?php _e('Command', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "gzip",
        "pro": 1
      },
      {
        "name": "img_exec_svg",
        "title": "<?php _e('SVG Command', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('External (CLI) command to optimize SVG images.', 'psn-pagespeed-ninja'); ?>",
        "type": "text",
        "default": "svgo {{TARGET}} -o {{TARGET}}",
        "pro": 1
      }
    ]
  },
  {
    "id": "modern-image-format",
    "title": "<?php _e('Serve images in next-gen formats', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "img_webp",
        "title": "<?php _e('Convert to WebP', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Automatically convert images to WebP format for browsers that support it.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "presets": {
          "optimal": 1,
          "ultra": 1,
          "experimental": 1
        }
      },
      {
        "name": "img_avif",
        "title": "<?php _e('Convert to AVIF', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Automatically convert images to AVIF format for browsers that support it.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "pro": 1,
        "presets": {
          "optimal": 1,
          "ultra": 1,
          "experimental": 1
        }
      }
    ]
  },
  {
    "id": "uses-responsive-images",
    "title": "<?php _e('Properly size images', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "img_srcset",
        "title": "<?php _e('Generate srcset', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Generate srcset attribute for images.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "pro": 1,
        "presets": {
          "safe": 0
        }
      },
      {
        "name": "img_srcsetwidth",
        "title": "<?php _e('Srcset Widths', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('List of srcset widths to generate.', 'psn-pagespeed-ninja'); ?>",
        "type": "text",
        "default": "360,720,1080,1284,1440,1920",
        "pro": 1
      }
    ]
  },
  {
    "id": "offscreen-images",
    "title": "<?php _e('Defer offscreen images', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "lazyload_method",
        "title": "<?php _e('Lazy Loading Method', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('You can disable lazy loading or choose between native browser\'s lazy loading and JavaScript-based advanced lazy loading.', 'psn-pagespeed-ninja'); ?>",
        "type": "select",
        "values": [
          {
            "": "<?php _e('Disabled', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "native": "<?php _e('Native', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "js": "<?php _e('JavaScript', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "native"
      },
      {
        "name": "img_lazyload",
        "title": "<?php _e('Lazy Load Images', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Lazy load images with the Lazy Load XT script. Significantly speeds up the loading of image and/or video-heavy webpages.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "safe": 0
        }
      },
      {
        "name": "img_lazyload_bg",
        "title": "<?php _e('Lazy Load Background Images', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Lazy load background images in style attribute.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "pro": 1
      },
      {
        "name": "img_lazyload_video",
        "title": "<?php _e('Lazy Load videos', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Lazy load videos with the Lazy Load XT script.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "safe": 0
        }
      },
      {
        "name": "img_lazyload_iframe",
        "title": "<?php _e('Lazy Load Iframes', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Lazy load iframes with the Lazy Load XT script.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "safe": 0
        }
      },
      {
        "name": "img_lazyload_youtube",
        "title": "<?php _e('Lazy Load YouTube', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Lazy load embedded YouTube iframes.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "pro": 1,
        "presets": {
          "experimental": 1
        }
      },
      {
        "name": "img_lazyload_lqip",
        "title": "<?php _e('Low-quality Image Placeholders', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Use low-quality image placeholders instead of empty areas.', 'psn-pagespeed-ninja'); ?>",
        "type": "select",
        "values": [
          {
            "none": "<?php _e('None', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "full": "<?php _e('Image', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "low": "<?php _e('Gradient', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "low"
      },
      {
        "name": "img_lazyload_embed",
        "title": "<?php _e('Inline Low-quality Images', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Inline low-quality images to avoid extra network requests.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "safe": 0
        }
      },
      {
        "name": "img_lazyload_edgey",
        "title": "<?php _e('Vertical Lazy Loading Threshold', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Expand the visible page area (viewport) in vertical direction by specified amount of pixels, so that images start to load even if they are not actually visible yet.', 'psn-pagespeed-ninja'); ?>",
        "type": "number",
        "units": "<?php _e('px', 'psn-pagespeed-ninja'); ?>",
        "default": 0,
        "presets": {
        }
      },
      {
        "name": "img_lazyload_skip",
        "title": "<?php _e('Skip First Images', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Skip lazy loading of specified number of images from the beginning of an HTML page (useful for logos and other images that are always visible in the above-the-fold area).', 'psn-pagespeed-ninja'); ?>",
        "type": "number",
        "default": 3,
        "presets": {
          "ultra": 1,
          "experimental": 0
        }
      },
      {
        "name": "img_lazyload_bg_skip",
        "title": "<?php _e('Skip First Background Images', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Skip lazy loading of specified number of background images from the beginning of an HTML page (useful for logos and other images that are always visible in the above-the-fold area).', 'psn-pagespeed-ninja'); ?>",
        "type": "number",
        "default": 0,
        "pro": 1
      },
      {
        "name": "img_lazyload_video_skip",
        "title": "<?php _e('Skip First Videos', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Skip lazy loading of specified number of videos from the beginning of an HTML page (useful for videos that are always visible in the above-the-fold area).', 'psn-pagespeed-ninja'); ?>",
        "type": "number",
        "default": 0,
        "pro": 1
      },
      {
        "name": "img_lazyload_iframe_skip",
        "title": "<?php _e('Skip First IFrames', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Skip lazy loading of specified number of iframes from the beginning of an HTML page (useful for iframes that are always visible in the above-the-fold area).', 'psn-pagespeed-ninja'); ?>",
        "type": "number",
        "default": 0,
        "pro": 1
      },
      {
        "name": "img_lazyload_noscript",
        "title": "<?php _e('Noscript Position', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Position to insert the original image wrapped in a noscript tag for browsers with disabled JavaScript (may be useful if your image styles rely on CSS selectors :first or :last). To not generate noscript tags, set this option to \'None\'.', 'psn-pagespeed-ninja'); ?>",
        "type": "select",
        "values": [
          {
            "after": "<?php _e('After', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "before": "<?php _e('Before', 'psn-pagespeed-ninja'); ?>"
          },
          {
            "none": "<?php _e('None', 'psn-pagespeed-ninja'); ?>"
          }
        ],
        "default": "after"
      }
    ]
  },
  {
    "id": "lcp-lazy-loaded",
    "title": "<?php _e('Largest Contentful Paint image was lazily loaded', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
    ]
  },
  {
    "id": "prioritize-lcp-image",
    "title": "<?php _e('Preload Largest Contentful Paint image', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
    ]
  },
  {
    "id": "unused-javascript",
    "title": "<?php _e('Reduce unused JavaScript', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
    ]
  },
  {
    "id": "efficient-animated-content",
    "title": "<?php _e('Use video formats for animated content', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
    ]
  },
  {
    "id": "unsized-images",
    "title": "<?php _e('Image elements have explicit width and height', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "img_size",
        "title": "<?php _e('Set width/height', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Ensure all images have width and height attributes to avoid layout shift.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "safe": 0
        }
      }
    ]
  },
  {
    "id": "non-composited-animations",
    "title": "<?php _e('Avoid non-composited animations', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
    ]
  },
  {
    "id": "bootup-time",
    "title": "<?php _e('JavaScript execution time', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "js_widgets",
        "title": "<?php _e('Optimize Integrations (Facebook, Twitter, etc.)', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Optimize the loading of popular JavaScript widgets like integrations with Facebook, Twitter, Google Plus, Gravatar etc.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "safe": 0
        }
      },
      {
        "name": "js_widgets_delay_async",
        "title": "<?php _e('Delay Async Scripts', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Delay loading of all asynchronous JavaScripts.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "presets": {
          "experimental": 1
        }
      },
      {
        "name": "js_widgets_delay_scripts",
        "title": "<?php _e('Delay Scripts', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Delay loading of specified JavaScripts.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "presets": {
          "experimental": 1
        }
      },
      {
        "name": "js_widgets_delay_scripts_list",
        "title": "<?php _e('Delay Scripts List', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Delay loading of the following JavaScripts (specify URL without protocol).', 'psn-pagespeed-ninja'); ?>",
        "type": "textarea",
        "default": "//connect.facebook.net/%LANG%/all.js\n//connect.facebook.net/%LANG%/sdk.js\n//platform.twitter.com/widgets.js\n//apis.google.com/js/api.js\n//apis.google.com/js/platform.js\n//apis.google.com/js/plusone.js\n//s.sharethis.com/loader.js\n//gravatar.com/js/gprofiles.js\n//s.gravatar.com/js/gprofiles.js\n//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js\n//reactandshare.azureedge.net/plugin/rns.js"
      }
    ]
  },
  {
    "id": "dom-size",
    "title": "<?php _e('Avoids an excessive DOM size', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "remove_objects",
        "title": "<?php _e('Remove Embedded Plugins', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Remove all embedded plugins like Flash, ActiveX, Silverlight, etc.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1
      },
      {
        "name": "dom_slim",
        "title": "<?php _e('Reduce DOM Size', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Hide extra DOM elements and reveal them after user interaction.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 0,
        "pro": 1,
        "presets": {
          "experimental": 1
        }
      },
      {
        "name": "dom_slim_elements",
        "title": "<?php _e('Elements to Hide', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Elements that will be hidden until user starts to interact with the page. The format is tag name optionally followed by attribute-based condition.', 'psn-pagespeed-ninja'); ?>",
        "type": "textarea",
        "default": "section\ndiv[class*=post-content]",
        "pro": 1
      },
      {
        "name": "dom_slim_max",
        "title": "<?php _e('Elements Threshold', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Number of body elements after which DOM reduction mode will be applied to Elements to Hide.', 'psn-pagespeed-ninja'); ?>",
        "type": "number",
        "default": 500,
        "pro": 1
      }
    ]
  },
  {
    "id": "viewport",
    "title": "<?php _e('Has a meta viewport tag with width or initial-scale', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "viewport_width",
        "title": "<?php _e('Viewport Width', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Viewport width in pixels. Set to 0 (zero) to use the device screen width (default).', 'psn-pagespeed-ninja'); ?>",
        "type": "number",
        "units": "<?php _e('px', 'psn-pagespeed-ninja'); ?>",
        "default": 0,
        "presets": {
        }
      }
    ]
  },
  {
    "id": "legacy-javascript",
    "title": "<?php _e('Avoid serving legacy JavaScript to modern browsers', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "js_polyfills",
        "title": "<?php _e('Polyfills', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('List of strings to detect polyfill JavaScript files and load them in legacy browsers only.', 'psn-pagespeed-ninja'); ?>",
        "type": "textarea",
        "default": "/wp-polyfill"
      }
    ]
  },
  {
    "id": "uses-passive-event-listeners",
    "title": "<?php _e('Does not use passive listeners to improve scrolling performance', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
      {
        "name": "passive_listeners",
        "title": "<?php _e('Force Passive Listeners', 'psn-pagespeed-ninja'); ?>",
        "tooltip": "<?php _e('Enables passive event listeners for mouse and touch events.', 'psn-pagespeed-ninja'); ?>",
        "type": "checkbox",
        "default": 1,
        "presets": {
          "safe": 0
        },
        "pro": 1
      }
    ]
  },
  {
    "id": "duplicated-javascript",
    "title": "<?php _e('Remove duplicate modules in JavaScript bundles', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
    ]
  },
  {
    "id": "third-party-summary",
    "title": "<?php _e('Minimize third-party usage', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
    ]
  },
  {
    "id": "third-party-facades",
    "title": "<?php _e('Lazy load third-party resources with facades', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
    ]
  },
  {
    "id": "mainthread-work-breakdown",
    "title": "<?php _e('Minimize main-thread work', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
    ]
  },
  {
    "id": "no-document-write",
    "title": "<?php _e('Avoids document.write()', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
    ]
  },
  {
    "id": "interactive",
    "title": "<?php _e('Time to Interactive', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
    ]
  },
  {
    "id": "max-potential-fid",
    "title": "<?php _e('Max Potential First Input Delay', 'psn-pagespeed-ninja'); ?>",
    "type": "speed",
    "items": [
    ]
  }
]