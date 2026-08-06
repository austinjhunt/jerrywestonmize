<?php
/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

defined('ABSPATH') || die('No script kiddies please!');

?>
<section class="wpa-events__empty" aria-labelledby="amelia-wpa-empty-title">
    <div class="wpa-events__empty-card">
        <h2 id="amelia-wpa-empty-title" class="wpa-events__empty-title"><?php esc_html_e('Event unavailable', 'wpamelia'); ?></h2>
        <p class="wpa-events__empty-text"><?php esc_html_e('This event is no longer available or could not be loaded.', 'wpamelia'); ?></p>
        <a class="wpa-events__empty-link" href="<?php echo esc_url(home_url('/')); ?>">
            <?php esc_html_e('Back to home', 'wpamelia'); ?>
        </a>
    </div>
</section>
