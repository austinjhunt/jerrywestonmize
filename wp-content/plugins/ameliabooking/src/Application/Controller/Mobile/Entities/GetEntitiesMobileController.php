<?php

namespace AmeliaBooking\Application\Controller\Mobile\Entities;

use AmeliaBooking\Application\Commands\Entities\GetEntitiesCommand;
use AmeliaBooking\Application\Controller\Mobile\MobileV1Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /mobile/v1/entities — filter-option payload for the staff app
 * (services via categories, employees, locations, packages).
 */
class GetEntitiesMobileController extends MobileV1Controller
{
    protected function instantiateCommand(Request $request, $args): GetEntitiesCommand
    {
        $command = new GetEntitiesCommand($args);

        $params = (array)$request->getQueryParams();
        unset($params['source']);

        $this->setArrayParams($params);

        $command->setField('params', $params);
        $command->setToken($request);
        $this->forceCabinetContext($command);

        return $command;
    }
}
