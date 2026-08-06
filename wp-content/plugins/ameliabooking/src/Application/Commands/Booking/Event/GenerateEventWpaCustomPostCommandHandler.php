<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Commands\Booking\Event;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\Booking\EventApplicationService;
use AmeliaBooking\Domain\Entity\Booking\Event\Event;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Infrastructure\WP\CustomPostTypes\Events\WpaEventsSync;

/**
 * Creates the WordPress wpa-events custom post for an event when missing (admin action).
 *
 * @package AmeliaBooking\Application\Commands\Booking\Event
 */
class GenerateEventWpaCustomPostCommandHandler extends CommandHandler
{
    /**
     * @param GenerateEventWpaCustomPostCommand $command
     *
     * @return CommandResult
     */
    public function handle(GenerateEventWpaCustomPostCommand $command)
    {
        $result = new CommandResult();

        /** @var EventApplicationService $eventApplicationService */
        $eventApplicationService = $this->container->get('application.booking.event.service');

        if (!$command->getPermissionService()->currentUserCanWrite(Entities::EVENTS)) {
            throw new AccessDeniedException('You are not allowed to manage events');
        }

        $eventId = (int)$command->getField('id');
        if ($eventId <= 0) {
            $result->setResult(CommandResult::RESULT_CONFLICT);
            $result->setMessage(__('Invalid event.', 'wpamelia'));

            return $result;
        }

        /** @var Event|null $event */
        $event = $eventApplicationService->getEventById(
            $eventId,
            [
                'fetchEventsPeriods'    => false,
                'fetchEventsTickets'    => false,
                'fetchEventsTags'       => false,
                'fetchEventsProviders'  => false,
                'fetchEventsImages'     => false,
                'fetchBookings'         => false,
                'fetchBookingsTickets'  => false,
                'fetchBookingsUsers'    => false,
                'fetchBookingsPayments' => false,
                'fetchBookingsCoupons'  => false,
                'fetchEventsOrganizer'  => false,
                'fetchEventsLocation'   => false,
            ]
        );

        if (!$event) {
            $result->setResult(CommandResult::RESULT_CONFLICT);
            $result->setMessage(__('Event not found.', 'wpamelia'));

            return $result;
        }

        $eventArray = $event->toArray();

        $postId = WpaEventsSync::findPostIdByAmeliaEventId($eventId);
        $alreadyExisted = $postId > 0;

        if (!$postId) {
            WpaEventsSync::createCptIfMissing($eventArray);
            $postId = WpaEventsSync::findPostIdByAmeliaEventId($eventId);
        }

        if (!$postId) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(__('Could not create the WordPress custom post for this event.', 'wpamelia'));

            return $result;
        }

        $permalink = get_permalink($postId);
        $editLink  = admin_url('post.php?post=' . $postId . '&action=edit');

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage(
            $alreadyExisted
                ? __('Custom post already exists for this event.', 'wpamelia')
                : __('Custom post created.', 'wpamelia')
        );
        $result->setData(
            [
                'postId'         => $postId,
                'permalink'      => $permalink ?: '',
                'editLink'       => $editLink,
                'alreadyExisted' => $alreadyExisted,
            ]
        );

        return $result;
    }
}
