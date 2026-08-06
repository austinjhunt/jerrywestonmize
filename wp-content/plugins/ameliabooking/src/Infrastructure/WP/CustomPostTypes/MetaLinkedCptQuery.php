<?php

/**
 * WordPress CPT lookups by a single post_meta value (Amelia entity id ↔ post).
 *
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\CustomPostTypes;

/**
 * Class MetaLinkedCptQuery
 *
 * @package AmeliaBooking\Infrastructure\WP\CustomPostTypes
 */
class MetaLinkedCptQuery
{
    /**
     * @param string $postType Registered CPT slug
     * @param string $metaKey  Meta key storing the Amelia entity id
     * @param int    $entityId Amelia entity id
     *
     * @return int Post ID or 0
     */
    public static function findPostIdByEntityId($postType, $metaKey, $entityId)
    {
        global $wpdb;

        $entityId = (int)$entityId;

        if ($entityId <= 0) {
            return 0;
        }

        $sql = $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
              AND pm.meta_key = %s
              AND pm.meta_value = %s
            WHERE p.post_type = %s
              AND p.post_status != 'trash'
            LIMIT 1",
            $metaKey,
            (string)$entityId,
            $postType
        );

        return (int)$wpdb->get_var($sql);
    }

    /**
     * @param string   $postType Registered CPT slug
     * @param string   $metaKey  Meta key storing the Amelia entity id
     * @param int[]    $entityIds
     *
     * @return array<int,int> Map entityId => postId
     */
    public static function findPostIdMapByEntityIds($postType, $metaKey, $entityIds)
    {
        global $wpdb;

        $entityIds = array_values(
            array_unique(
                array_filter(
                    array_map('intval', (array)$entityIds),
                    static function ($id) {
                        return $id > 0;
                    }
                )
            )
        );

        if (empty($entityIds)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($entityIds), '%s'));

        $sql = $wpdb->prepare(
            "SELECT pm.meta_value AS entity_id, p.ID AS post_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
              AND pm.meta_key = %s
            WHERE p.post_type = %s
              AND p.post_status != 'trash'
              AND pm.meta_value IN ({$placeholders})",
            array_merge(
                [$metaKey, $postType],
                array_map('strval', $entityIds)
            )
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        $map = [];

        foreach ((array)$rows as $row) {
            $entityId = isset($row['entity_id']) ? (int)$row['entity_id'] : 0;
            $postId = isset($row['post_id']) ? (int)$row['post_id'] : 0;

            if ($entityId > 0 && $postId > 0) {
                $map[$entityId] = $postId;
            }
        }

        return $map;
    }
}
