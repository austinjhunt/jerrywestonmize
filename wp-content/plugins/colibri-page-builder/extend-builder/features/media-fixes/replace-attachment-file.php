<?php

namespace ExtendBuilder;

/**
 * Swaps the file behind an existing media library attachment while keeping
 * its attachment ID, URL and filename untouched, then regenerates the
 * registered image sizes so cropped/resized copies match the new file.
 */
class MediaFileReplace
{
    public static function findAttachmentIdByFilename($filename)
    {
        global $wpdb;

        // A bare "LIKE '%filename'" also matches anything that merely ends
        // with this filename - e.g. a "cropped-<filename>" derivative from
        // this plugin's own image cropper. Requiring a real path boundary
        // (the whole value, or right after a "/") excludes that.
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND (meta_value = %s OR meta_value LIKE %s) ORDER BY post_id ASC LIMIT 1",
            $filename,
            '%/' . $wpdb->esc_like($filename)
        ));

        return $attachment_id ? (int) $attachment_id : 0;
    }

    public static function replace($attachment_id, $replacement_path)
    {
        if (!file_exists($replacement_path)) {
            return false;
        }

        $target_path = get_attached_file($attachment_id);

        // wp_is_writable() (unlike native is_writable(), unreliable for
        // directories on Windows) lets us bail on a read-only uploads dir
        // before attempting anything, rather than a copy() failure later.
        if (!$target_path || !wp_is_writable(dirname($target_path))) {
            return false;
        }

        // Stage the new file before touching anything old - if this copy
        // fails, the attachment is left completely untouched.
        $staged_path = $target_path . '.new-' . uniqid();

        if (!copy($replacement_path, $staged_path)) {
            return false;
        }

        // What core itself uses when trashing an attachment: removes every
        // generated size, backup size and the original file via
        // wp_delete_file_from_directory() (directory-scoped, filterable)
        // rather than a raw unlink(). Skipped entirely if metadata hasn't
        // been written yet (e.g. this ran while a manual upload's size
        // generation was still in flight) - there's nothing to clean up in
        // that case, and handing core an empty/guessed shape is exactly
        // what it doesn't expect. Also wrapped: core's own loop over
        // $meta['sizes'] throws a fatal TypeError on real sites where a
        // size entry isn't shaped as expected (e.g. written by another
        // plugin) - leaving old size files as harmless clutter beats that
        // bubbling up through admin_init and breaking wp-admin.
        $old_metadata = wp_get_attachment_metadata($attachment_id);
        $backup_sizes = get_post_meta($attachment_id, '_wp_attachment_backup_sizes', true);

        if ($old_metadata) {
            try {
                wp_delete_attachment_files($attachment_id, (array) $old_metadata, (array) $backup_sizes, $target_path);
            } catch (\Throwable $e) {
                error_log('WritingHandImageFix: wp_delete_attachment_files failed for attachment ' . $attachment_id . ': ' . $e->getMessage());
            }
        }

        // rename() is atomic on a POSIX filesystem - no window where a
        // reader sees a missing file, unlike overwriting via copy(). The
        // fallback only matters if the delete above didn't fully succeed.
        if (!@rename($staged_path, $target_path)) {
            if (!copy($staged_path, $target_path)) {
                @unlink($staged_path);
                return false;
            }
            @unlink($staged_path);
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Without this, a replacement image above the "big image" threshold
        // gets scaled into a "-scaled" copy and _wp_attached_file repointed
        // to it - silently changing the filename/URL we need to keep.
        add_filter('big_image_size_threshold', '__return_false');
        $metadata = wp_generate_attachment_metadata($attachment_id, $target_path);
        remove_filter('big_image_size_threshold', '__return_false');

        // Full-size file is already correct either way; don't report
        // success if metadata generation failed, so it gets retried.
        if (!$metadata) {
            return false;
        }

        wp_update_attachment_metadata($attachment_id, $metadata);
        delete_post_meta($attachment_id, '_wp_attachment_backup_sizes');

        self::replaceCroppedChildren($attachment_id, $replacement_path);

        return true;
    }

    // Attachments created by this plugin's image cropper are separate
    // media library entries with post_parent set to the source image
    // (this is the relationship the Media Library's "Uploaded to" column
    // reflects). There's no way to derive a matching crop of the new
    // photo from the old crop coordinates, so each child just gets the
    // same in-place swap as the parent - same call, reused as-is - which
    // keeps its own ID/filename/URL but means it's no longer necessarily
    // cropped to whatever aspect ratio it originally was.
    private static function replaceCroppedChildren($parent_id, $replacement_path)
    {
        $children = get_posts(array(
            'post_type'      => 'attachment',
            'post_parent'    => $parent_id,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ));

        foreach ($children as $child_id) {
            self::replace($child_id, $replacement_path);
        }
    }
}
