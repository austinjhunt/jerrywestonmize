<?php
/**
 * @var array $eventData
 *
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

defined('ABSPATH') || die('No script kiddies please!');

use AmeliaBooking\Infrastructure\WP\Translations\LiteBackendStrings;

$begin = amelia_wpa_events_begin_block($eventData);
$loc   = amelia_wpa_events_location_line($eventData);
$title = !empty($eventData['name']) ? $eventData['name'] : get_the_title();
$displayStatus = !empty($eventData['displayStatus']) ? (string)$eventData['displayStatus'] : 'closed';
$statusLabel = amelia_wpa_events_display_status_label($displayStatus);
$showSlots = amelia_wpa_events_show_slots_for_status($displayStatus);
$slots = $showSlots ? amelia_wpa_events_slots_left_event($eventData) : null;

$listingPrice = amelia_wpa_events_public_price_for_listing($eventData);
$price        = $listingPrice['amount'];
$showFrom     = !empty($listingPrice['show_from']);

$vatLabel = '';
if (!empty($ameliaEventId)) {
    $vatLabel = amelia_wpa_events_price_vat_pill_label((int)$ameliaEventId);
}
$vatLabel = apply_filters('amelia_wpa_events_price_vat_label', $vatLabel);

?>
<div class="wpa-events__meta-row">
    <?php if ($begin) : ?>
        <div class="wpa-events__begin">
            <span class="wpa-events__begin-label"><?php echo esc_html($begin['label']); ?></span>
            <div class="wpa-events__begin-date">
                <div class="wpa-events__begin-day"><?php echo esc_html($begin['day']); ?></div>
                <div class="wpa-events__begin-mon"><?php echo esc_html($begin['month']); ?><?php echo esc_html(', '); ?><?php echo esc_html($begin['year']); ?></div>
            </div>
            <span class="wpa-events__begin-time"><?php echo esc_html($begin['time']); ?></span>
        </div>
    <?php endif; ?>

    <div class="wpa-events__meta-main">
        <h1 class="wpa-events__event-title"><?php echo esc_html($title); ?></h1>
        <?php if ($loc !== '') : ?>
            <p class="wpa-events__event-location"><?php echo $loc; ?></p>
        <?php endif; ?>
        <div class="wpa-events__event-status-row">
            <span class="wpa-events__pill wpa-events__pill--<?php echo esc_attr($displayStatus); ?>">
                <?php echo esc_html($statusLabel); ?>
            </span>
            <?php if ($slots !== null) : ?>
                <span class="wpa-events__slots">
                    <strong><?php echo esc_html((string)$slots); ?></strong>
                    <?php echo esc_html(LiteBackendStrings::get('event_slots_left')); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="wpa-events__price-block">
        <?php if ($price !== null) : ?>
            <div class="wpa-events__price-line">
                <?php if ($showFrom) : ?>
                    <span class="wpa-events__price-from"><?php echo esc_html(LiteBackendStrings::get('from')); ?></span>
                <?php endif; ?>
                <span class="wpa-events__price-value"><?php echo esc_html(amelia_wpa_events_format_money_display($price)); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($vatLabel !== '') : ?>
            <span class="wpa-events__vat-pill"><?php echo esc_html((string)$vatLabel); ?></span>
        <?php endif; ?>
    </div>
</div>
