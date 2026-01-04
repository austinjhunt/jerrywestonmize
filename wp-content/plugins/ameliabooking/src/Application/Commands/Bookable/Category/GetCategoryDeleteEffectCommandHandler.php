<?php

namespace AmeliaBooking\Application\Commands\Bookable\Category;

use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\Bookable\BookableApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Bookable\Service\Service;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\ServiceRepository;
use AmeliaBooking\Domain\Collection\Collection;
use Interop\Container\Exception\ContainerException;
use Slim\Exception\ContainerValueNotFoundException;

/**
 * Class GetCategoryDeleteEffectCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Bookable\Category
 */
class GetCategoryDeleteEffectCommandHandler extends CommandHandler
{
    /**
     * @param GetCategoryDeleteEffectCommand $command
     *
     * @return CommandResult
     * @throws ContainerValueNotFoundException
     * @throws AccessDeniedException
     * @throws QueryExecutionException
     * @throws ContainerException
     * @throws InvalidArgumentException
     */
    public function handle(GetCategoryDeleteEffectCommand $command)
    {
        if (!$command->getPermissionService()->currentUserCanRead(Entities::SERVICES)) {
            throw new AccessDeniedException('You are not allowed to read categories');
        }

        $result = new CommandResult();

        /** @var BookableApplicationService $bookableAS */
        $bookableAS = $this->getContainer()->get('application.bookable.service');

        /** @var ServiceRepository $serviceRepository */
        $serviceRepository = $this->getContainer()->get('domain.bookable.service.repository');

        /** @var Collection $services */
        $services = $serviceRepository->getByCriteria(['categories' => [$command->getArg('id')]]);

        $categoryServiceIds = [];

        /** @var Service $service */
        foreach ($services->getItems() as $service) {
            $categoryServiceIds[] = $service->getId()->getValue();
        }

        $messageKey = '';
        $messageData = null;

        if ($categoryServiceIds) {
            $appointmentsCount = $bookableAS->getAppointmentsCountForServices($categoryServiceIds);

            if ($appointmentsCount['futureAppointments'] > 0) {
                $messageKey = 'red_delete_category_effect_future';
                $messageData = ['count' => $appointmentsCount['futureAppointments']];
            } elseif ($appointmentsCount['packageAppointments']) {
                $messageKey = 'red_delete_category_effect_package';
            } elseif ($appointmentsCount['pastAppointments'] > 0) {
                $messageKey = 'red_delete_category_effect_past';
                $messageData = ['count' => $appointmentsCount['pastAppointments']];
            }
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully retrieved message.');
        $result->setData(
            [
                'valid'   => !($categoryServiceIds && ($appointmentsCount['futureAppointments'] || $appointmentsCount['packageAppointments'])),
                'messageKey'  => $messageKey,
                'messageData' => $messageData,
            ]
        );

        return $result;
    }
}
