<?php
/**
 * @var array $eventData
 *
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

defined('ABSPATH') || die('No script kiddies please!');

$organizer  = amelia_wpa_events_primary_host($eventData);
$staffCards = amelia_wpa_events_staff_cards($eventData);
$defaultHostPicture = defined('AMELIA_URL') ? AMELIA_URL . 'public/img/default-employee.svg' : '';

if (!$organizer && !$staffCards) {
    return;
}

?>
<div class="wpa-events__host-list">
    <?php if ($organizer) : ?>
        <div class="wpa-events__host">
            <div class="wpa-events__host-avatar">
                <?php if (!empty($organizer['picture'] ?? '')) : ?>
                    <img src="<?php echo esc_url($organizer['picture']); ?>" alt="" width="48" height="48" loading="lazy" decoding="async" />
                <?php elseif ($defaultHostPicture !== '') : ?>
                    <img src="<?php echo esc_url($defaultHostPicture); ?>" alt="" width="48" height="48" loading="lazy" decoding="async" />
                <?php endif; ?>
            </div>
            <div class="wpa-events__host-text">
                <span class="wpa-events__host-name"><?php echo esc_html($organizer['name']); ?></span>
                <span class="wpa-events__host-role"><?php echo esc_html($organizer['role']); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($staffCards as $staff) : ?>
        <div class="wpa-events__host">
            <div class="wpa-events__host-avatar">
                <?php if (!empty($staff['picture'] ?? '')) : ?>
                    <img src="<?php echo esc_url($staff['picture']); ?>" alt="" width="48" height="48" loading="lazy" decoding="async" />
                <?php elseif ($defaultHostPicture !== '') : ?>
                    <img src="<?php echo esc_url($defaultHostPicture); ?>" alt="" width="48" height="48" loading="lazy" decoding="async" />
                <?php endif; ?>
            </div>
            <div class="wpa-events__host-text">
                <span class="wpa-events__host-name"><?php echo esc_html($staff['name']); ?></span>
                <span class="wpa-events__host-role"><?php echo esc_html($staff['role']); ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>
