<?php
/**
 * Left column — matches Figma "Event Info" (node 2873:21278).
 *
 * @var array $eventData
 *
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

defined('ABSPATH') || die('No script kiddies please!');

?>
<div class="wpa-events__col wpa-events__col--event">
    <?php require __DIR__ . '/carousel.php'; ?>
    <?php require __DIR__ . '/meta-row.php'; ?>
    <?php require __DIR__ . '/timetable.php'; ?>
    <?php require __DIR__ . '/description-block.php'; ?>
    <div class="wpa-events__divider"></div>
    <?php require __DIR__ . '/host-block.php'; ?>
</div>
