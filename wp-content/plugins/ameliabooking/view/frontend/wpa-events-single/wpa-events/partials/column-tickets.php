<?php
/**
 * Tickets column mount — UI is rendered by Vue (WpaEventsTicketsSidebar via Teleport).
 *
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

defined('ABSPATH') || die('No script kiddies please!');

use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;

?>
<div
    id="wpa-events-tickets-mount"
    class="wpa-events__col wpa-events__col--tickets"
    role="complementary"
    aria-label="<?php echo esc_attr(BackendStrings::get('tickets')); ?>"
></div>
