<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Commands\Booking\Event;

use AmeliaBooking\Application\Commands\Command;

/**
 * Triggers creation of the wpa-events CPT for an Amelia event (series root only).
 *
 * @package AmeliaBooking\Application\Commands\Booking\Event
 */
class GenerateEventWpaCustomPostCommand extends Command
{
    /**
     * @param array $args
     */
    public function __construct($args)
    {
        parent::__construct($args);
        if (isset($args['id'])) {
            $this->setField('id', $args['id']);
        }
    }
}
