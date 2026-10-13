<?php

namespace AmeliaBooking\Application\Controller\Outlook;

use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Commands\Outlook\WatchOutlookCalendarNotificationCommand;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaVendor\Psr\Http\Message\ResponseInterface as Response;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class OutlookCalendarNotificationController
 *
 * @package AmeliaBooking\Application\Controller\Outlook
 */
class OutlookCalendarNotificationController extends Controller
{
    protected $allowedFields = [
        'value',
        'validationToken',
    ];

    /**
     * @param Request  $request
     * @param Response $response
     * @param          $args
     * @param bool     $validApiCall
     *
     * @return Response
     */
    public function __invoke(Request $request, Response $response, $args, $validApiCall = false)
    {
        $queryParams = $request->getQueryParams();

        if (!empty($queryParams['validationToken'])) {
            $response = $response
                ->withStatus(self::STATUS_OK)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');

            $response->getBody()->write((string)$queryParams['validationToken']);

            return $response;
        }

        $response = parent::__invoke($request, $response, $args, $validApiCall);

        // Notifications are processed after the response is sent. Declaring the length lets Graph treat the
        // response as complete without waiting for the connection to close, which matters where
        // fastcgi_finish_request() is unavailable.
        $bodySize = $response->getBody()->getSize();

        return $bodySize === null ? $response : $response->withHeader('Content-Length', (string)$bodySize);
    }

    /**
     * @param Request $request
     * @param         $args
     *
     * @return WatchOutlookCalendarNotificationCommand
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new WatchOutlookCalendarNotificationCommand($args);

        $queryParams = $request->getQueryParams();
        if (!empty($queryParams['validationToken'])) {
            $command->setField('validationToken', $queryParams['validationToken']);

            return $command;
        }

        $requestBody = $request->getParsedBody();
        if (!is_array($requestBody)) {
            $decodedBody = json_decode((string)$request->getBody(), true);
            $requestBody = is_array($decodedBody) ? $decodedBody : [];
        }

        if (is_array($requestBody)) {
            $this->setCommandFields($command, $requestBody);
        }

        return $command;
    }
}
