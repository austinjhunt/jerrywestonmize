<?php
/**
 * @var array $eventData
 *
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

defined('ABSPATH') || die('No script kiddies please!');

use AmeliaBooking\Infrastructure\WP\Translations\LiteBackendStrings;

if (empty($eventData['description'])) {
    return;
}

?>
<section class="wpa-events__block">
    <h2 class="wpa-events__block-label"><?php echo esc_html(LiteBackendStrings::get('description')); ?></h2>
    <div class="wpa-events__desc-wrap" data-amelia-wpa-desc>
        <div class="wpa-events__desc" data-amelia-wpa-desc-body>
            <?php echo apply_filters('the_content', $eventData['description']); ?>
        </div>
    </div>
</section>
