<?php defined('ABSPATH') || die(); ?>
[
  {
    "name": "safe",
    "title": "<?php _e('Safe', 'psn-pagespeed-ninja'); ?>",
    "tooltip": "<?php _e('Optimizations that are compatible with most of themes and plugins, but may result in not so high Google\'s PageSpeed Insight scores.', 'psn-pagespeed-ninja'); ?>",
    "wizard_description": "<?php _e('Ideal for balanced optimization and stability. Compatible with most themes and plugins.', 'psn-pagespeed-ninja'); ?>"
  },
  {
    "name": "compact",
    "title": "<?php _e('Compact', 'psn-pagespeed-ninja'); ?>",
    "tooltip": "<?php _e('Optimizations that require small amount of additional disk space (i.e. disabled image processing, loading of external URLs, etc.).', 'psn-pagespeed-ninja'); ?>",
    "wizard_description": "<?php _e('Ideal for resource limitations. Minimizes disk space by disabling resource-intensive processes.', 'psn-pagespeed-ninja'); ?>"
  },
  {
    "name": "optimal",
    "title": "<?php _e('Optimal', 'psn-pagespeed-ninja'); ?>",
    "tooltip": "<?php _e('Optimizations that are suitable for most of websites.', 'psn-pagespeed-ninja'); ?>",
    "wizard_description": "<?php _e('Ideal for most sites. Effective, reliable and well-rounded performance boost across different configurations.', 'psn-pagespeed-ninja'); ?>"
  },
  {
    "name": "ultra",
    "title": "<?php _e('Ultra', 'psn-pagespeed-ninja'); ?>",
    "tooltip": "<?php _e('Highest level of optimizations (excluding experimental ones).', 'psn-pagespeed-ninja'); ?>",
    "wizard_description": "<?php _e('Ideal for pushing the boundaries. Highest level of optimizations, excluding experimental settings.', 'psn-pagespeed-ninja'); ?>"
  },
  {
    "name": "experimental",
    "title": "<?php _e('Experimental', 'psn-pagespeed-ninja'); ?>",
    "tooltip": "<?php _e('All kinds of optimization, including experimental and unstable ones.', 'psn-pagespeed-ninja'); ?>",
    "wizard_description": "<?php _e('Ideal for the adventurous. Includes all available optimizations. Be prepared for quirks and possible bugs.', 'psn-pagespeed-ninja'); ?>"
  }
]