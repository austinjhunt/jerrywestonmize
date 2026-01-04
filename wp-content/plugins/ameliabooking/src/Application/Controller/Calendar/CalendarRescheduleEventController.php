<?php

namespace AmeliaBooking\Application\Controller\Calendar;

use AmeliaBooking\Application\Commands\Calendar\CalendarRescheduleEventCommand;
use AmeliaBooking\Application\Commands\Calendar\GetCalendarSlotAvailabilityCommand;
use AmeliaBooking\Application\Commands\Command;
use AmeliaBooking\Application\Controller\Controller;
use Slim\Http\Request;

class CalendarRescheduleEventController extends Controller
{
    public $allowedFields = [
        'bookingStart',
        'appointmentId'
    ];

    /**
     * @param Request $request
     * @param array   $args
     *
     * @return Command
     */
    protected function instantiateCommand(Request $request, $args): Command
    {
        $command = new CalendarRescheduleEventCommand($args);

        $queryParams = $request->getParams();

        $this->setCommandFields($command, $queryParams);

        return $command;
    }
}
