<?php

namespace AmeliaBooking\Application\Controller\Booking\Appointment;

use AmeliaBooking\Application\Commands\Booking\Appointment\UpdateAppointmentNoteCommand;
use AmeliaBooking\Application\Controller\Controller;
use RuntimeException;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class UpdateAppointmentNoteController
 *
 * @package AmeliaBooking\Application\Controller\Booking\Appointment
 */
class UpdateAppointmentNoteController extends Controller
{
    /**
     * Fields allowed from the front-end for this endpoint
     *
     * @var array
     */
    public $allowedFields = [
        'id',
        'internalNotes',
    ];

    /**
     * @param Request $request
     * @param         $args
     *
     * @return UpdateAppointmentNoteCommand
     * @throws RuntimeException
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new UpdateAppointmentNoteCommand($args);

        $requestBody = $request->getParsedBody();
        $this->setCommandFields($command, $requestBody);
        $command->setToken($request);

        $params = (array)$request->getQueryParams();

        if (isset($params['source'])) {
            $command->setPage($params['source']);
        }

        return $command;
    }
}
