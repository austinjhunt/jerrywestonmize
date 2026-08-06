<?php

/**
 * Registers all Amelia-managed WordPress custom post types and related hooks.
 *
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\CustomPostTypes;

use AmeliaBooking\Infrastructure\WP\CustomPostTypes\Events\WpaEventsBootstrap;
use AmeliaBooking\Infrastructure\WP\CustomPostTypes\Events\WpaEventsPostType;

/**
 * Class CustomPostTypesBootstrap
 *
 * @package AmeliaBooking\Infrastructure\WP\CustomPostTypes
 */
class CustomPostTypesBootstrap
{
    /**
     * Wire CPT modules (per-entity bootstraps live under CustomPostTypes/{Entity}/).
     */
    public static function init()
    {
        WpaEventsBootstrap::init();
    }

    /**
     * Plugin activation: flush rules / CPT registration for all managed types.
     */
    public static function onActivation()
    {
        WpaEventsPostType::onActivation();
    }
}
