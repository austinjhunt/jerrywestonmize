<?php

/**
 * Syncs Amelia events to the wpa-events CPT.
 *
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\CustomPostTypes\Events;

use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\WP\CustomPostTypes\CptEntityCreateLock;
use AmeliaBooking\Infrastructure\WP\CustomPostTypes\MetaLinkedCptQuery;
use AmeliaBooking\Infrastructure\WP\SettingsService\SettingsStorage;
use AmeliaBooking\Infrastructure\WP\Translations\LiteBackendStrings;

/**
 * Class WpaEventsSync
 *
 * @package AmeliaBooking\Infrastructure\WP\CustomPostTypes\Events
 */
class WpaEventsSync
{
    private const WPA_EVENT_CREATE_LOCK_TTL = 30;

    private const WPA_EVENT_CREATE_LOCK_PREFIX = 'amelia_wpa_event_lock_';

    /** @var SettingsService|null */
    private static $settingsService = null;

    /**
     * Wire WordPress hooks to Amelia do_action events.
     */
    public static function registerHooks()
    {
        add_action('amelia_after_event_added', [self::class, 'onEventAdded'], 10, 1);
        add_action('amelia_after_event_updated', [self::class, 'onEventUpdated'], 10, 3);
        add_action('amelia_after_event_deleted', [self::class, 'onEventDeleted'], 10, 1);
        add_action('amelia_after_events_deleted', [self::class, 'onEventsDeleted'], 10, 1);
        add_action('amelia_after_event_status_updated', [self::class, 'onEventVisibilityStatusUpdated'], 10, 3);
    }

    /**
     * @param array|null $event
     */
    public static function onEventAdded($event)
    {
        if (!is_array($event) || empty($event['id'])) {
            return;
        }

        if (!self::isAutoCreateEventCustomPostEnabled()) {
            return;
        }

        if (!self::isSeriesRootForCpt($event)) {
            return;
        }

        self::createCptIfMissing($event);
    }

    /**
     * @param array|null $newEvent
     * @param array|null $oldEvent
     * @param bool       $applyGlobally
     */
    public static function onEventUpdated($newEvent, $oldEvent, $applyGlobally)
    {
        unset($oldEvent, $applyGlobally);

        if (!is_array($newEvent) || empty($newEvent['id'])) {
            return;
        }

        if (!self::isSeriesRootForCpt($newEvent)) {
            return;
        }

        $postId = self::findPostIdByAmeliaEventId((int)$newEvent['id']);

        if (!$postId) {
            if (self::isAutoCreateEventCustomPostEnabled()) {
                self::createCptIfMissing($newEvent);
                $postId = self::findPostIdByAmeliaEventId((int)$newEvent['id']);
            }

            if (!$postId) {
                return;
            }
        }

        self::updateCptFromAmeliaEvent($postId, $newEvent);
    }

    /**
     * @param array|null $event
     */
    public static function onEventDeleted($event)
    {
        if (!is_array($event) || empty($event['id'])) {
            return;
        }

        if (!self::isSeriesRootForCpt($event)) {
            return;
        }

        self::trashCptByAmeliaEventId((int)$event['id']);
    }

    /**
     * @param array $deletedEvents
     */
    public static function onEventsDeleted($deletedEvents)
    {
        if (!is_array($deletedEvents)) {
            return;
        }

        foreach ($deletedEvents as $event) {
            self::onEventDeleted(is_array($event) ? $event : null);
        }
    }

    /**
     * Visibility route uses status "hidden" / "visible".
     *
     * @param array|null $event
     * @param string     $requestedStatus
     * @param bool       $applyGlobally
     */
    public static function onEventVisibilityStatusUpdated($event, $requestedStatus, $applyGlobally)
    {
        unset($applyGlobally);

        if (!in_array($requestedStatus, ['hidden', 'visible'], true)) {
            return;
        }

        if (!is_array($event) || empty($event['id'])) {
            return;
        }

        if (!self::isSeriesRootForCpt($event)) {
            return;
        }

        $ameliaEventId = (int)$event['id'];
        $postId = self::findPostIdByAmeliaEventId($ameliaEventId);
        $show = $requestedStatus === 'visible';

        if (!$postId) {
            if ($show && self::isAutoCreateEventCustomPostEnabled()) {
                self::createCptIfMissing($event);
                $postId = self::findPostIdByAmeliaEventId($ameliaEventId);
            }

            if (!$postId) {
                return;
            }
        }

        $update = [
            'ID'          => $postId,
            'post_status' => $show ? 'publish' : 'draft',
        ];

        wp_update_post($update, true);
    }

    /**
     * @param array $event
     */
    public static function createCptIfMissing(array $event)
    {
        if (empty($event['id'])) {
            return;
        }

        $ameliaEventId = (int)$event['id'];

        if ($ameliaEventId <= 0) {
            return;
        }

        if (self::findPostIdByAmeliaEventId($ameliaEventId)) {
            return;
        }

        $lockToken = CptEntityCreateLock::acquire(
            self::WPA_EVENT_CREATE_LOCK_PREFIX,
            $ameliaEventId,
            self::WPA_EVENT_CREATE_LOCK_TTL
        );

        if ($lockToken === false) {
            return;
        }

        try {
            if (self::findPostIdByAmeliaEventId($ameliaEventId)) {
                return;
            }

            $title = isset($event['name']) ? trim((string)$event['name']) : '';
            if ($title === '') {
                $title = LiteBackendStrings::get('wpa_events_default_post_title');
            }

            $postarr = [
                'post_type'    => WpaEventsConstants::POST_TYPE,
                'post_status'  => !empty($event['show']) ? 'publish' : 'draft',
                'post_title'   => $title,
                'post_content' => '',
                'post_name'    => '',
            ];

            $postId = wp_insert_post($postarr, true);

            if (is_wp_error($postId) || !$postId) {
                return;
            }

            update_post_meta((int)$postId, WpaEventsConstants::META_AMELIA_EVENT_ID, $ameliaEventId);
        } finally {
            CptEntityCreateLock::release(
                self::WPA_EVENT_CREATE_LOCK_PREFIX,
                $ameliaEventId,
                (string)$lockToken
            );
        }
    }

    /**
     * @param int   $postId
     * @param array $event
     */
    public static function updateCptFromAmeliaEvent($postId, array $event)
    {
        $title = isset($event['name']) ? trim((string)$event['name']) : '';
        if ($title === '') {
            $title = LiteBackendStrings::get('wpa_events_default_post_title');
        }

        $update = [
            'ID'          => (int)$postId,
            'post_title'  => $title,
            'post_status' => !empty($event['show']) ? 'publish' : 'draft',
        ];

        wp_update_post($update, true);
    }

    /**
     * @param int $ameliaEventId
     *
     * @return int
     */
    public static function findPostIdByAmeliaEventId($ameliaEventId)
    {
        return MetaLinkedCptQuery::findPostIdByEntityId(
            WpaEventsConstants::POST_TYPE,
            WpaEventsConstants::META_AMELIA_EVENT_ID,
            (int)$ameliaEventId
        );
    }

    /**
     * @param int[] $ameliaEventIds
     *
     * @return array<int,int> Map: ameliaEventId => postId
     */
    public static function findPostIdByAmeliaEventIds($ameliaEventIds)
    {
        return MetaLinkedCptQuery::findPostIdMapByEntityIds(
            WpaEventsConstants::POST_TYPE,
            WpaEventsConstants::META_AMELIA_EVENT_ID,
            (array)$ameliaEventIds
        );
    }

    /**
     * @param int $ameliaEventId
     */
    public static function trashCptByAmeliaEventId($ameliaEventId)
    {
        $postId = self::findPostIdByAmeliaEventId($ameliaEventId);

        if ($postId) {
            wp_trash_post($postId);
        }
    }

    /**
     * Series root: DB uses parentId NULL; some flows may use parentId === id.
     *
     * @param array $event
     *
     * @return bool
     */
    public static function isSeriesRootForCpt(array $event)
    {
        if (empty($event['id'])) {
            return false;
        }

        $id = (int)$event['id'];

        if (!array_key_exists('parentId', $event) || $event['parentId'] === null || $event['parentId'] === '') {
            return true;
        }

        return (int)$event['parentId'] === $id;
    }

    /**
     * @return bool
     */
    private static function isAutoCreateEventCustomPostEnabled()
    {
        if (self::$settingsService === null) {
            self::$settingsService = new SettingsService(new SettingsStorage());
        }

        return self::$settingsService->getSetting(
            'appointments',
            'automaticallyCreateEventCustomPost',
            false
        ) === true;
    }
}
