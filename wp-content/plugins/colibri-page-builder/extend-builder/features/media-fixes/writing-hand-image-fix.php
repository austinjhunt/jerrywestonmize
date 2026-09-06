<?php

namespace ExtendBuilder;

/**
 * One-off fix: swaps a specific demo image for a replacement on every site
 * that already has it in the media library, keeping the same filename/URL
 * so nothing that references it (page content, other plugins) breaks.
 */
class WritingHandImageFix
{
    const OPTION_NAME = 'colibri_media_fix_writing_hand_image_done';
    const TRIES_OPTION_NAME = 'colibri_media_fix_writing_hand_image_tries';
    const CRON_HOOK = 'colibri_media_fix_writing_hand_image_cron';
    const MAX_TRIES = 3;
    const FILENAME = 'writing-hand-glass-adventure-travel-spring-931328-pxhere-com.jpg';

    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'onAdminInit'));

        // Covers automatic (WP-Cron) updates, which never fire admin_init.
        // Runs the *old* code when this plugin is the one being updated
        // (PHP already had it loaded before the upgrader ran), so it can't
        // fire on the update that first introduces this hook - the cron
        // nudge below and admin_init above cover that gap instead.
        add_action('upgrader_process_complete', array(__CLASS__, 'onUpgraderComplete'));

        add_action(self::CRON_HOOK, array(__CLASS__, 'run'));

        // init() runs fresh on every request regardless of which code is
        // "old" elsewhere, so this is what actually catches a site that
        // auto-updated straight into this feature and is never opened in
        // wp-admin: queue a one-off background run instead of doing the
        // file work inline on a random visitor's request.
        if (!\get_option(self::OPTION_NAME) && !wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_single_event(time(), self::CRON_HOOK);
        }
    }

    public static function onAdminInit()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        self::run();
    }

    public static function onUpgraderComplete()
    {
        // No capability check: WP-Cron's background updater runs with no
        // logged-in user at all, and this is WP core calling us, not
        // arbitrary request input.
        self::run();
    }

    public static function run()
    {
        // onAdminInit hangs off admin_init, which fires on every wp-admin
        // page load - an uncaught error anywhere below (including inside
        // WP core functions we call, given messy enough real-world data)
        // would otherwise break the entire dashboard, repeatedly, instead
        // of just failing this one-off fix.
        try {
            self::doRun();
        } catch (\Throwable $e) {
            error_log('WritingHandImageFix: ' . $e->getMessage());
        }
    }

    private static function doRun()
    {
        if (\get_option(self::OPTION_NAME)) {
            return;
        }

        $replacement_path = extend_builder_path() . '/assets/media-fixes/' . self::FILENAME;

        if (!file_exists($replacement_path)) {
            return;
        }

        $attachment_id = MediaFileReplace::findAttachmentIdByFilename(self::FILENAME);

        // Nothing to fix on this site - stop checking on every future call.
        if (!$attachment_id) {
            \update_option(self::OPTION_NAME, true);
            return;
        }

        // Only mark this done once the swap actually succeeds, so a
        // failure gets retried later instead of being silently given up on.
        if (MediaFileReplace::replace($attachment_id, $replacement_path)) {
            \update_option(self::OPTION_NAME, true);
            return;
        }

        // Cap the retries so a permanently read-only host doesn't recheck
        // forever; replace() never leaves things half-done, so giving up
        // just leaves the original image in place.
        $tries = intval(\get_option(self::TRIES_OPTION_NAME, 0));

        if ($tries + 1 >= self::MAX_TRIES) {
            \update_option(self::OPTION_NAME, true);
        } else {
            \update_option(self::TRIES_OPTION_NAME, $tries + 1);
        }
    }
}

WritingHandImageFix::init();
