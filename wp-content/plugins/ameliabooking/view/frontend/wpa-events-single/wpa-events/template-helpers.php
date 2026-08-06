<?php
/**
 * Helpers for wpa-events single template (scoped; loaded once per request).
 *
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

defined('ABSPATH') || die('No script kiddies please!');

use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;
use AmeliaBooking\Infrastructure\WP\Translations\LiteBackendStrings;
use AmeliaBooking\Infrastructure\Licence\Licence;
use AmeliaBooking\Infrastructure\Licence\LicenceConstants;

if (!function_exists('amelia_wpa_events_container')) {
    function amelia_wpa_events_container(): ?Container
    {
        static $container = null;
        static $failed = false;

        if ($failed) {
            return null;
        }

        if ($container instanceof Container) {
            return $container;
        }

        if (!defined('AMELIA_PATH')) {
            $failed = true;

            return null;
        }

        try {
            /** @var Container $container */
            $container = require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php';
        } catch (Throwable $e) {
            $container = null;
            $failed    = true;
        }

        return $container;
    }
}

if (!function_exists('amelia_wpa_events_format_datetime')) {
    function amelia_wpa_events_format_datetime(string|null $mysqlDatetime): string
    {
        if (empty($mysqlDatetime) || !is_string($mysqlDatetime)) {
            return '';
        }

        try {
            $utc = new DateTimeZone('UTC');
            $dt  = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mysqlDatetime, $utc);

            if (!$dt) {
                return esc_html($mysqlDatetime);
            }

            $local = $dt->setTimezone(wp_timezone());
            $fmt   = get_option('date_format') . ' ' . get_option('time_format');

            return esc_html(wp_date($fmt, $local->getTimestamp()));
        } catch (Throwable $e) {
            return esc_html($mysqlDatetime);
        }
    }
}

if (!function_exists('amelia_wpa_events_parse_period_mysql')) {
    function amelia_wpa_events_parse_period_mysql(string|null $mysqlDatetime): ?DateTimeImmutable
    {
        if (empty($mysqlDatetime) || !is_string($mysqlDatetime)) {
            return null;
        }

        try {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mysqlDatetime, wp_timezone());

            return $dt ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('amelia_wpa_events_timetable_rows')) {
    function amelia_wpa_events_timetable_rows(string|null $periodStart, string|null $periodEnd): array
    {
        $s = amelia_wpa_events_parse_period_mysql($periodStart);
        $e = amelia_wpa_events_parse_period_mysql($periodEnd);

        if (!$s || !$e) {
            return [];
        }

        $s = $s->setTimezone(wp_timezone());
        $e = $e->setTimezone(wp_timezone());

        $startDate = $s->setTime(0, 0, 0);
        $endDate   = $e->setTime(0, 0, 0);
        $endTime   = wp_date('H:i:s', $e->getTimestamp());

        if ($endTime === '00:00:00') {
            $endDate = $endDate->modify('-1 day');
        }

        $rows = [];
        $date = $startDate;

        while ($date <= $endDate) {
            $rows[] = [
                'date'      => wp_date(get_option('date_format'), $date->getTimestamp()),
                'timeStart' => wp_date(get_option('time_format'), $s->getTimestamp()),
                'timeEnd'   => $endTime === '00:00:00'
                    ? '24:00'
                    : wp_date(get_option('time_format'), $e->getTimestamp()),
                'sort'      => $date->getTimestamp(),
            ];

            $date = $date->modify('+1 day');
        }

        return $rows;
    }
}

if (!function_exists('amelia_wpa_events_begin_block')) {
    function amelia_wpa_events_begin_block(array $eventData): ?array
    {
        if (empty($eventData['periods'][0]['periodStart'])) {
            return null;
        }

        $s = amelia_wpa_events_parse_period_mysql($eventData['periods'][0]['periodStart']);

        if (!$s) {
            return null;
        }

        $s = $s->setTimezone(wp_timezone());

        return [
            'label' => LiteBackendStrings::get('event_begins'),
            'day'   => wp_date('j', $s->getTimestamp()),
            'month' => wp_date('M', $s->getTimestamp()),
            'year'  => wp_date('Y', $s->getTimestamp()),
            'time'  => wp_date(get_option('time_format'), $s->getTimestamp()),
        ];
    }
}

if (!function_exists('amelia_wpa_events_carousel_sources')) {
    function amelia_wpa_events_carousel_sources(array $eventData): array
    {
        $urls = [];

        if (!empty($eventData['pictureFullPath'])) {
            $urls[] = (string) $eventData['pictureFullPath'];
        }

        if (!empty($eventData['gallery']) && is_array($eventData['gallery'])) {
            foreach ($eventData['gallery'] as $row) {
                if (!empty($row['pictureFullPath'])) {
                    $u = (string) $row['pictureFullPath'];
                    if (!in_array($u, $urls, true)) {
                        $urls[] = $u;
                    }
                }
            }
        }

        return $urls;
    }
}

if (!function_exists('amelia_wpa_events_slots_left_event')) {
    function amelia_wpa_events_slots_left_event(array $eventData): ?int
    {
        $customTickets = $eventData['customTickets'] ?? [];

        if (($eventData['customPricing'] ?? false) === true && $customTickets !== []) {
            $slotsLeft = 0;

            foreach ($customTickets as $ticket) {
                if ($ticket['enabled'] !== true) {
                    continue;
                }

                $slotsLeft += max(0, (int) $ticket['spots'] - (int) $ticket['sold']);
            }

            return $slotsLeft;
        }

        $max = isset($eventData['maxCapacity']) ? (int) $eventData['maxCapacity'] : 0;
        if ($max <= 0) {
            return null;
        }

        $sold = isset($eventData['spotsSold']) ? (int) $eventData['spotsSold'] : 0;

        return max(0, $max - $sold);
    }
}

if (!function_exists('amelia_wpa_events_format_price_number_with_settings')) {
    function amelia_wpa_events_format_price_number_with_settings(array $paymentSettings, float $price): string
    {
        $thousandSeparatorMap = [',', '.', ' ', ' '];
        $decimalSeparatorMap = ['.', ',', '.', ','];

        $sepIndex = isset($paymentSettings['priceSeparator']) ? (int) $paymentSettings['priceSeparator'] - 1 : 0;
        $sepIndex = max(0, min(3, $sepIndex));

        $thousandSeparator = $thousandSeparatorMap[$sepIndex];
        $decimalSeparator = $decimalSeparatorMap[$sepIndex];
        $decimals         = isset($paymentSettings['priceNumberOfDecimals'])
            ? (int) $paymentSettings['priceNumberOfDecimals']
            : 2;

        return number_format($price, $decimals, $decimalSeparator, $thousandSeparator);
    }
}

if (!function_exists('amelia_wpa_events_format_money_display')) {
    function amelia_wpa_events_format_money_display($amount): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        if (!is_numeric($amount)) {
            return esc_html((string) $amount);
        }

        $value = (float) $amount;
        $c     = amelia_wpa_events_container();

        if ($c) {
            try {
                /** @var \AmeliaBooking\Domain\Services\Settings\SettingsService $settingsService */
                $settingsService = $c->get('domain.settings.service');
                $paymentSettings = $settingsService->getCategorySettings('payments');

                if (!empty($paymentSettings['hideCurrencySymbolFrontend'])) {
                    return esc_html(amelia_wpa_events_format_price_number_with_settings($paymentSettings, $value));
                }

                /** @var \AmeliaBooking\Application\Services\Helper\HelperService $helper */
                $helper = $c->get('application.helper.service');
                return esc_html($helper->getFormattedPrice($value));
            } catch (Throwable $e) {
                // Safe fallback below.
            }
        }

        return esc_html(number_format($value, 2, '.', ''));
    }
}

if (!function_exists('amelia_wpa_events_public_price_for_listing')) {
    function amelia_wpa_events_public_price_for_listing(array $eventData): array
    {
        $tickets = (!empty($eventData['customPricing'])
            && !empty($eventData['customTickets'])
            && is_array($eventData['customTickets']))
            ? $eventData['customTickets']
            : [];

        if ($tickets !== []) {
            $candidates = [];

            foreach ($tickets as $ticket) {
                if (!is_array($ticket)) {
                    continue;
                }

                $enabled = !array_key_exists('enabled', $ticket)
                    || $ticket['enabled'] === true
                    || $ticket['enabled'] === 1
                    || $ticket['enabled'] === '1';

                if (!$enabled) {
                    continue;
                }

                $tPrice = 0.0;
                if (isset($ticket['price']) && is_numeric($ticket['price'])) {
                    $tPrice = (float) $ticket['price'];
                }

                if (!empty($ticket['dateRangePrice']) && is_numeric($ticket['dateRangePrice'])) {
                    $tPrice = (float) $ticket['dateRangePrice'];
                }

                $candidates[] = $tPrice;
            }

            $minPositive = null;
            foreach ($candidates as $p) {
                if ($p > 0 && ($minPositive === null || $p < $minPositive)) {
                    $minPositive = $p;
                }
            }

            if ($minPositive !== null) {
                return ['amount' => $minPositive, 'show_from' => true];
            }

            return ['amount' => null, 'show_from' => false];
        }

        if (!isset($eventData['price']) || !is_numeric($eventData['price'])) {
            return ['amount' => null, 'show_from' => false];
        }

        $base = (float) $eventData['price'];

        return $base > 0
            ? ['amount' => $base, 'show_from' => false]
            : ['amount' => null, 'show_from' => false];
    }
}

if (!function_exists('amelia_wpa_events_price_vat_pill_label')) {
    function amelia_wpa_events_price_vat_pill_label(int $eventId): string
    {
        if ($eventId <= 0) {
            return '';
        }

        $c = amelia_wpa_events_container();
        if (!$c) {
            return '';
        }

        try {
            /** @var \AmeliaBooking\Domain\Services\Settings\SettingsService $settingsService */
            $settingsService = $c->get('domain.settings.service');

            if (!$settingsService->isFeatureEnabled('tax')) {
                return '';
            }

            $taxService = $c->get('application.tax.service');
            if (!is_object($taxService) || !method_exists($taxService, 'getTaxData') || !method_exists($taxService, 'getAll')) {
                return '';
            }

            $taxes = $taxService->getAll();
            $json  = $taxService->getTaxData($eventId, Entities::EVENT, $taxes);

            if ($json === null || $json === '' || $json === '[]') {
                return '';
            }

            $taxesSettings = $settingsService->getSetting('payments', 'taxes');
            $excluded      = !empty($taxesSettings['excluded']);

            return $excluded ? '+ ' . BackendStrings::get('total_tax_colon') : BackendStrings::get('incl_tax');
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('amelia_wpa_events_location_line')) {
    function amelia_wpa_events_location_line(array $eventData): string
    {
        $location = $eventData['customLocation'] ?? ($eventData['location']['name'] ?? '');

        return $location !== '' ? esc_html((string) $location) : '';
    }
}

if (!function_exists('amelia_wpa_events_person_card')) {
    function amelia_wpa_events_person_card(array $person, string $role): ?array
    {
        $first = $person['firstName'] ?? '';
        $last  = $person['lastName'] ?? '';
        $name  = trim($first . ' ' . $last);

        if ($name === '') {
            return null;
        }

        $picture = '';
        if (!empty($person['pictureFullPath'])) {
            $picture = (string) $person['pictureFullPath'];
        } elseif (!empty($person['pictureThumbPath'])) {
            $picture = (string) $person['pictureThumbPath'];
        }

        return [
            'name'    => $name,
            'picture' => $picture,
            'role'    => $role,
        ];
    }
}

if (!function_exists('amelia_wpa_events_primary_host')) {
    function amelia_wpa_events_primary_host(array $eventData): ?array
    {
        $organizerLabel = LiteBackendStrings::get('event_organizer');

        if (!empty($eventData['organizer']) && is_array($eventData['organizer'])) {
            $card = amelia_wpa_events_person_card($eventData['organizer'], $organizerLabel);
            if ($card !== null) {
                return $card;
            }
        }

        return null;
    }
}

if (!function_exists('amelia_wpa_events_staff_cards')) {
    /**
     * @return list<array{name: string, picture: string, role: string}>
     */
    function amelia_wpa_events_staff_cards(array $eventData): array
    {
        $providers = $eventData['providers'] ?? [];
        if (!is_array($providers) || $providers === []) {
            return [];
        }

        $isLite = !Licence::hasLicenseAccess(LicenceConstants::STARTER);
        $staffLabel = $isLite ? '' : LiteBackendStrings::get('event_staff');
        $cards      = [];
        $seen       = [];

        foreach ($providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }

            $card = amelia_wpa_events_person_card($provider, $staffLabel);
            if ($card === null) {
                continue;
            }

            $key = md5($card['name'] . '|' . $card['picture']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $cards[] = $card;
        }

        return $cards;
    }
}

if (!function_exists('amelia_wpa_events_sidebar_tickets_payload')) {
    function amelia_wpa_events_sidebar_tickets_payload(array $eventData, bool $useWaitingList = false): array
    {
        if (empty($eventData['customPricing'])
            || empty($eventData['customTickets'])
            || !is_array($eventData['customTickets'])
        ) {
            return [];
        }

        $payload = [];
        $maxCustomCapacity = !empty($eventData['maxCustomCapacity']);

        foreach ($eventData['customTickets'] as $ticket) {
            if (!is_array($ticket) || empty($ticket['name'])) {
                continue;
            }

            $enabled = !array_key_exists('enabled', $ticket)
                || $ticket['enabled'] === true
                || $ticket['enabled'] === 1
                || $ticket['enabled'] === '1';

            if (!$enabled) {
                continue;
            }

            $tid = !empty($ticket['id']) ? (int) $ticket['id'] : 0;
            if ($tid <= 0) {
                continue;
            }

            $spots = isset($ticket['spots']) ? (int) $ticket['spots'] : 0;
            $sold  = isset($ticket['sold']) ? (int) $ticket['sold'] : 0;
            $left = max(0, $spots - $sold);

            if ($useWaitingList && $left <= 0) {
                $waiting = isset($ticket['waiting']) ? (int) $ticket['waiting'] : 0;
                if (!$maxCustomCapacity) {
                    $waitingListSpots = isset($ticket['waitingListSpots']) ? (int) $ticket['waitingListSpots'] : 0;
                    $left = max(0, $waitingListSpots - $waiting);
                } else {
                    $left = isset($eventData['waitingListSpotsLeft'])
                        ? max(0, (int) $eventData['waitingListSpotsLeft'])
                        : max(
                            0,
                            (int) ($eventData['waitingCapacity'] ?? 0) - (int) ($eventData['waiting'] ?? 0)
                        );
                }
            }

            $price = null;
            if (!empty($ticket['dateRangePrice']) && is_numeric($ticket['dateRangePrice'])) {
                $price = $ticket['dateRangePrice'];
            } elseif (isset($ticket['price'])) {
                $price = $ticket['price'];
            }

            $payload[] = [
                'id'             => $tid,
                'name'           => (string) $ticket['name'],
                'spots'          => $spots,
                'sold'           => $sold,
                'left'           => $left,
                'price'          => $price,
                'priceFormatted' => ($price !== null && $price !== '' && is_numeric($price))
                    ? amelia_wpa_events_format_money_display($price)
                    : '',
            ];
        }

        return $payload;
    }
}

if (!function_exists('amelia_wpa_events_display_status_label')) {
    function amelia_wpa_events_display_status_label(string $displayStatus): string
    {
        $labels = [
            'open'      => LiteBackendStrings::get('open'),
            'closed'    => LiteBackendStrings::get('closed'),
            'canceled'  => LiteBackendStrings::get('canceled'),
            'full'      => LiteBackendStrings::get('full'),
            'upcoming'  => LiteBackendStrings::get('upcoming'),
        ];

        return $labels[$displayStatus] ?? LiteBackendStrings::get('closed');
    }
}

if (!function_exists('amelia_wpa_events_show_slots_for_status')) {
    function amelia_wpa_events_show_slots_for_status(string $displayStatus): bool
    {
        return in_array($displayStatus, ['open', 'upcoming'], true);
    }
}

if (!function_exists('amelia_wpa_events_attendees_payload')) {
    /**
     * Sidebar persons selector for spot-priced events (mirrors EventInfo BringingAnyone).
     *
     * @param array                                                                 $eventData
     * @param array{available?: bool, spotsLeft?: int, maxExtraPeople?: int|null}|null $waitingListAvailability
     *                                                                 From WaitingListService::getEventWaitingListAvailability().
     *
     * @return array{min: int, max: int, default: int, slotsLeft: int}|null
     */
    function amelia_wpa_events_attendees_payload(array $eventData, ?array $waitingListAvailability = null): ?array
    {
        if (!empty($eventData['customPricing'])) {
            return null;
        }

        if (empty($eventData['bringingAnyone'])) {
            return null;
        }

        $capacity = 0;
        $maxExtraPeople = null;
        $useWaitingList = !empty($waitingListAvailability['available']);

        if ($useWaitingList) {
            $capacity = isset($waitingListAvailability['spotsLeft'])
                ? max(0, (int) $waitingListAvailability['spotsLeft'])
                : 0;

            if (array_key_exists('maxExtraPeople', $waitingListAvailability)
                && $waitingListAvailability['maxExtraPeople'] !== null
            ) {
                $maxExtraPeople = (int) $waitingListAvailability['maxExtraPeople'];
            }
        } else {
            if (isset($eventData['places']) && is_numeric($eventData['places'])) {
                $capacity = max(0, (int) $eventData['places']);
            } else {
                $slots = amelia_wpa_events_slots_left_event($eventData);
                $capacity = $slots !== null ? max(0, $slots) : 0;
            }

            if (isset($eventData['maxExtraPeople']) && is_numeric($eventData['maxExtraPeople'])) {
                $maxExtraPeople = (int) $eventData['maxExtraPeople'];
            }
        }

        if ($capacity <= 1) {
            return null;
        }

        $maxPersons = $capacity;
        if ($maxExtraPeople !== null && $maxExtraPeople > 0 && $capacity > $maxExtraPeople) {
            $maxPersons = $maxExtraPeople + 1;
        }

        if ($maxPersons <= 1) {
            return null;
        }

        return [
            'min'       => 1,
            'max'       => $maxPersons,
            'default'   => 1,
            'slotsLeft' => $capacity,
        ];
    }
}

if (!function_exists('amelia_wpa_events_enqueue_shell_block_support_styles')) {
    /** Flush block-supports CSS after template parts (before wp_footer). */
    function amelia_wpa_events_enqueue_shell_block_support_styles(bool $useBlockThemeShell): void
    {
        if ($useBlockThemeShell && function_exists('wp_enqueue_stored_styles')) {
            wp_enqueue_stored_styles();
        }
    }
}
