<?php
/**
 * @var array $eventData
 *
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

defined('ABSPATH') || die('No script kiddies please!');

$slides = amelia_wpa_events_carousel_sources($eventData);

if ($slides === []) {
    return;
}

$slideCount = count($slides);

?>
<div class="wpa-events__carousel" data-amelia-wpa-carousel style="<?php echo esc_attr('--wpa-slides: ' . (int)$slideCount . ';'); ?>">
    <div class="wpa-events__carousel-viewport">
        <div class="wpa-events__carousel-track" data-carousel-track>
            <?php foreach ($slides as $i => $src) : ?>
                <div class="wpa-events__carousel-slide" data-carousel-slide="<?php echo (int)$i; ?>">
                    <img src="<?php echo esc_url($src); ?>" alt="" loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>" decoding="async" />
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($slides) > 1) : ?>
            <button type="button" class="wpa-events__carousel-nav wpa-events__carousel-nav--prev" data-carousel-prev aria-label="<?php esc_attr_e('Previous image', 'wpamelia'); ?>">
                <span aria-hidden="true">&lsaquo;</span>
            </button>
            <button type="button" class="wpa-events__carousel-nav wpa-events__carousel-nav--next" data-carousel-next aria-label="<?php esc_attr_e('Next image', 'wpamelia'); ?>">
                <span aria-hidden="true">&rsaquo;</span>
            </button>
            <div class="wpa-events__carousel-dots" aria-label="<?php esc_attr_e('Slides', 'wpamelia'); ?>">
                <?php foreach ($slides as $i => $src) : ?>
                    <button type="button" class="wpa-events__carousel-dot<?php echo $i === 0 ? ' is-active' : ''; ?>" data-carousel-dot="<?php echo (int)$i; ?>" aria-label="<?php echo esc_attr(sprintf(__('Slide %d', 'wpamelia'), (int)$i + 1)); ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
