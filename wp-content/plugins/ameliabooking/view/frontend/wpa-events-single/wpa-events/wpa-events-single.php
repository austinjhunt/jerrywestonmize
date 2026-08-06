<?php
/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

defined('ABSPATH') || die('No script kiddies please!');

use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Application\Services\Booking\EventApplicationService;
use AmeliaBooking\Application\Services\WaitingList\WaitingListService;
use AmeliaBooking\Infrastructure\WP\CustomPostTypes\Events\WpaEventsConstants;
use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;
use AmeliaBooking\Infrastructure\WP\Translations\LiteBackendStrings;

$ameliaWpaTplDir = __DIR__;
require $ameliaWpaTplDir . '/template-helpers.php';

$ameliaWpaUseClassicThemeShell = (!function_exists('wp_is_block_theme') || !wp_is_block_theme())
    && locate_template(['header.php'], false, false)
    && locate_template(['footer.php'], false, false);

$ameliaWpaUseBlockThemeShell = function_exists('wp_is_block_theme')
    && wp_is_block_theme()
    && function_exists('block_header_area')
    && function_exists('block_footer_area');

if ($ameliaWpaUseClassicThemeShell) {
    get_header();
} else {
    ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php wp_head(); ?>
</head>
<body <?php body_class('wpa-events-single-template'); ?>>
    <?php
    if (function_exists('wp_body_open')) {
        wp_body_open();
    }
    if ($ameliaWpaUseBlockThemeShell) {
        block_header_area();
    }
}

$postId = get_the_ID();
$ameliaEventId = (int)get_post_meta($postId, WpaEventsConstants::META_AMELIA_EVENT_ID, true);

$eventData = null;
$eventInfo = null;
$eventDisplayStatus = null;
$eventEntity = null;
$eventApplicationService = null;
$waitingListAvailability = null;
$ameliaWpaBookingCounter = 'wpa-events';

if ($ameliaEventId > 0) {
    try {
        $container = amelia_wpa_events_container();
        if ($container instanceof Container) {
            /** @var EventApplicationService $eventApplicationService */
            $eventApplicationService = $container->get('application.booking.event.service');

            $eventEntity = $eventApplicationService->getEventById(
                $ameliaEventId,
                [
                    'fetchEventsPeriods'   => true,
                    'fetchEventsTickets'   => true,
                    'fetchEventsProviders' => true,
                    'fetchEventsImages'    => true,
                    'fetchEventsOrganizer' => true,
                    'fetchEventsLocation'  => true,
                    'fetchOccupancy'       => true,
                ]
            );

            if ($eventEntity) {
                /** @var WaitingListService $waitingListService */
                $waitingListService = $container->get('application.waitingList.service');

                $eventInfo = $eventApplicationService->getEventInfo($eventEntity, true);
                $eventDisplayStatus = $eventApplicationService->eventDisplayStatus($eventEntity, $eventInfo);
                $waitingListAvailability = $waitingListService->getEventWaitingListAvailability(
                    $eventEntity,
                    $eventInfo
                );
                $eventData = array_merge(
                    $eventEntity->toArray(),
                    $eventInfo,
                    [
                        'displayStatus' => $eventDisplayStatus,
                        'waitingListSpotsLeft' => $waitingListAvailability['spotsLeft'] ?? 0,
                    ]
                );
            }
        }
    } catch (Throwable $e) {
        $eventData = null;
        $eventInfo = null;
        $eventDisplayStatus = null;
        $eventEntity = null;
        $eventApplicationService = null;
        $waitingListAvailability = null;
    }
}

$wpaTicketsPayload  = [];
$wpaAttendeesPayload = null;
$wpaTicketStrings   = [];
$showSidebarColumn = false;
$wpaWaitingListAvailable = false;
$wpaEventBookable = false;

if ($eventData && $ameliaEventId > 0) {
    $wpaWaitingListAvailable = !empty($waitingListAvailability['available']);
    $wpaEventBookable = !empty($eventInfo['bookable']) || $wpaWaitingListAvailable;
    $wpaTicketsPayload   = amelia_wpa_events_sidebar_tickets_payload($eventData, $wpaWaitingListAvailable);
    $wpaAttendeesPayload = amelia_wpa_events_attendees_payload($eventData, $waitingListAvailability);
    $showSidebarColumn   = $wpaTicketsPayload !== [] || $wpaAttendeesPayload !== null;

    $wpaTicketStrings = [
        'selectTickets'     => BackendStrings::get('event_select_tickets'),
        'selectTicketsSub'  => BackendStrings::get('event_tickets_context'),
        'ticketsLeft'       => BackendStrings::get('event_tickets_left'),
        'attendeesTitle'    => LiteBackendStrings::get('event_book_persons'),
        'attendeesSubtitle' => LiteBackendStrings::get('event_bringing'),
        'slotsLeft'         => BackendStrings::get('event_slots_left'),
        'bookEvent'         => $wpaWaitingListAvailable
            ? BackendStrings::get('join_waiting_list')
            : BackendStrings::get('event_book_event'),
    ];
}

?>
<div
    id="wpa-events-landing"
    class="amelia-wpa wpa-events__shell"
    data-amelia-event-id="<?php echo esc_attr((string)$ameliaEventId); ?>"
    data-amelia-booking-counter="<?php echo esc_attr((string)$ameliaWpaBookingCounter); ?>"
>
    <div class="wpa-events__inner<?php echo $eventData && $ameliaEventId > 0 ? ' wpa-events__inner--has-booking' : ''; ?>">
        <?php if (!$eventData) : ?>
            <?php require $ameliaWpaTplDir . '/partials/unavailable.php'; ?>
        <?php else : ?>
            <div class="wpa-events__columns<?php echo $ameliaEventId > 0 && !$showSidebarColumn ? ' wpa-events__columns--book-only' : ''; ?>">
                <?php require $ameliaWpaTplDir . '/partials/column-event.php'; ?>
                <?php if ($showSidebarColumn) : ?>
                    <?php require $ameliaWpaTplDir . '/partials/column-tickets.php'; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($eventData && $ameliaEventId > 0) : ?>
            <div id="wpa-events-footer-mount" class="wpa-events__footer-mount"></div>
        <?php endif; ?>
    </div>
</div>
<?php if ($eventData && $ameliaEventId > 0) : ?>
    <div
        id="amelia-v2-booking-<?php echo esc_attr((string)$ameliaWpaBookingCounter); ?>"
        class="amelia-v2-booking amelia-wpa-booking-root"
        style="position:absolute; width:0; height:0; overflow:hidden; pointer-events:none;"
        aria-hidden="true"
    >
        <wpa-events-landing-form-wrapper></wpa-events-landing-form-wrapper>
    </div>
    <script>
      window.ameliaShortcodeData = window.ameliaShortcodeData || [];
      window.ameliaShortcodeData.push(
        <?php
        echo wp_json_encode(
            [
                'hasApiCall' => true,
                'trigger' => '',
                'trigger_type' => '',
                'triggered_form' => 'ecf',
                'in_dialog' => '',
                'counter' => (string)$ameliaWpaBookingCounter,
                'employee' => '',
                'eventId' => (string)$ameliaEventId,
                'eventRecurring' => 0,
                'eventTag' => '',
                'locationId' => '',
                'wpaTickets' => $wpaTicketsPayload,
                'wpaAttendees' => $wpaAttendeesPayload,
                'wpaTicketStrings' => $wpaTicketStrings,
                'wpaEventBookable' => $wpaEventBookable,
                'wpaEventWaitingListAvailable' => $wpaWaitingListAvailable,
                'wpaWaitingListSpotsLeft' => (int) ($waitingListAvailability['spotsLeft'] ?? 0),
                'wpaMaxCustomCapacity' => !empty($eventData['maxCustomCapacity']),
                'wpaEventDisplayStatus' => $eventDisplayStatus,
            ]
        );
        ?>
      );
    </script>
<?php endif; ?>

<?php
if ($ameliaWpaUseClassicThemeShell) {
    get_footer();
} else {
    if ($ameliaWpaUseBlockThemeShell) {
        block_footer_area();
    }
    amelia_wpa_events_enqueue_shell_block_support_styles($ameliaWpaUseBlockThemeShell);
    
    wp_footer();
    ?></body>
</html><?php
}
