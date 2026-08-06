<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Controller\Booking\Event;

use AmeliaBooking\Application\Commands\Booking\Event\GenerateEventWpaCustomPostCommand;
use AmeliaBooking\Application\Controller\Controller;
use RuntimeException;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /events/{id}/wpa-custom-post — ensure wpa-events CPT exists for the Amelia event.
 *
 * @package AmeliaBooking\Application\Controller\Booking\Event
 */
class GenerateEventWpaCustomPostController extends Controller
{
    /**
     * @param Request $request
     * @param array   $args
     *
     * @return GenerateEventWpaCustomPostCommand
     *
     * @throws RuntimeException
     */
    protected function instantiateCommand(Request $request, $args)
    {
        return new GenerateEventWpaCustomPostCommand($args);
    }
}
