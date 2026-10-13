<?php

namespace AmeliaBooking\Application\Controller\Mobile\Users;

use AmeliaBooking\Application\Commands\User\Customer\GetCustomersCommand;
use AmeliaBooking\Application\Controller\Mobile\MobileV1Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /mobile/v1/customers — paginated, searchable customer list for staff-app filters.
 */
class GetCustomersMobileController extends MobileV1Controller
{
    protected function instantiateCommand(Request $request, $args): GetCustomersCommand
    {
        $command = new GetCustomersCommand($args);

        $params = (array)$request->getQueryParams();
        unset($params['source']);

        $this->setArrayParams($params, ['noShow', 'includeCustomers']);

        $command->setField('params', $params);
        $command->setToken($request);
        $this->forceCabinetContext($command);

        return $command;
    }
}
