<?php
/**
 * @var array $eventData
 *
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

defined('ABSPATH') || die('No script kiddies please!');

if (empty($eventData['periods']) || !is_array($eventData['periods'])) {
    return;
}

$rows = [];

foreach ($eventData['periods'] as $period) {
    if (empty($period['periodStart']) || empty($period['periodEnd'])) {
        continue;
    }

    $rows = array_merge(
        $rows,
        amelia_wpa_events_timetable_rows($period['periodStart'], $period['periodEnd'])
    );
}

if (!$rows) {
    return;
}

usort(
    $rows,
    static function (array $a, array $b): int {
        return ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0);
    }
);

?>
<section class="wpa-events__block wpa-events__block--tight">
    <h2 class="wpa-events__block-label"><?php esc_html_e('Timetable', 'wpamelia'); ?></h2>
    <div class="wpa-events__timetable-line">
        <?php foreach ($rows as $row) : ?>
            <div class="wpa-events__timetable-row">
                <span class="wpa-events__timetable-date"><?php echo esc_html($row['date']); ?></span>
                <span class="wpa-events__timetable-time">
                    <?php echo esc_html($row['timeStart'] . ' - ' . $row['timeEnd']); ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
