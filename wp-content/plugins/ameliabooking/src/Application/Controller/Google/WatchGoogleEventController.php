<?php

namespace AmeliaBooking\Application\Controller\Google;

use AmeliaBooking\Application\Commands\Google\WatchGoogleEventCommand;
use AmeliaBooking\Application\Controller\Controller;
use RuntimeException;
use AmeliaVendor\Psr\Http\Message\ResponseInterface as Response;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class WatchGoogleEventController
 *
 * @package AmeliaBooking\Application\Controller\Google
 */
class WatchGoogleEventController extends Controller
{
    /**
     * Fields that can be received from front-end
     *
     * @var array
     */
    public $allowedFields = [
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
        $response = parent::__invoke($request, $response, $args, $validApiCall);

        // The push is processed after the response is sent. Declaring the length lets Google treat the response as
        // complete without waiting for the connection to close, which matters where fastcgi_finish_request() is
        // unavailable.
        $bodySize = $response->getBody()->getSize();

        return $bodySize === null ? $response : $response->withHeader('Content-Length', (string)$bodySize);
    }

    /**
     * Instantiates the WatchGoogleEventCommand to hand it over to the Command Handler
     *
     * @param Request $request
     * @param         $args
     *
     * @return WatchGoogleEventCommand
     * @throws RuntimeException
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new WatchGoogleEventCommand($args);

        $requestBody = $request->getParsedBody() ?? [];
        $this->setCommandFields($command, $requestBody);

        return $command;
    }
}
