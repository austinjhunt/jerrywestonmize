<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Controller\CustomField;

use AmeliaBooking\Application\Commands\CustomField\BatchCustomFieldsCommand;
use AmeliaBooking\Application\Controller\Controller;
use Slim\Http\Request;

/**
 * Class BatchCustomFieldsController
 *
 * @package AmeliaBooking\Application\Controller\CustomField
 */
class BatchCustomFieldsController extends Controller
{
    /**
     * Fields for custom fields that can be received from front-end
     *
     * @var array
     */
    protected $allowedFields = [
        'customFields'
    ];

    /**
     * Instantiates the Batch Custom Field command to hand it over to the Command Handler
     *
     * @param Request $request
     * @param         $args
     *
     * @return mixed
     * @throws \RuntimeException
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new BatchCustomFieldsCommand($args);

        $requestBody = $request->getParsedBody();

        $this->filter($requestBody);
        $this->setCommandFields($command, $requestBody);

        return $command;
    }
}
