<?php

namespace AmeliaBooking\Application\Commands\CustomField;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\Entity\EntityApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Bookable\Service\Service;
use AmeliaBooking\Domain\Entity\Booking\Event\Event;
use AmeliaBooking\Domain\Entity\CustomField\CustomField;
use AmeliaBooking\Domain\Entity\CustomField\CustomFieldOption;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Factory\CustomField\CustomFieldFactory;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\Id;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\CustomField\CustomFieldEventRepository;
use AmeliaBooking\Infrastructure\Repository\CustomField\CustomFieldOptionRepository;
use AmeliaBooking\Infrastructure\Repository\CustomField\CustomFieldRepository;
use AmeliaBooking\Infrastructure\Repository\CustomField\CustomFieldServiceRepository;
use Interop\Container\Exception\ContainerException;
use Slim\Exception\ContainerValueNotFoundException;

/**
 * Class BatchCustomFieldsCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\CustomField
 */
class BatchCustomFieldsCommandHandler extends CommandHandler
{
    public function handle($command)
    {
        if (!$command->getPermissionService()->currentUserCanWrite(Entities::CUSTOM_FIELDS)) {
            throw new AccessDeniedException('You are not allowed to update custom fields.');
        }

        $result = new CommandResult();

        $fields = $command->getField('customFields'); // expects array of all fields, new and existing

        /** @var CustomFieldRepository $customFieldRepository */
        $customFieldRepository = $this->container->get('domain.customField.repository');

        $positions = [];
        foreach ($fields as $index => $field) {
            /** @var CustomField $customField */
            $customField = CustomFieldFactory::create($field);
            if (empty($field['id']) || $field['id'] == 0) {
                if (!$customField instanceof CustomField) {
                    $result->setResult(CommandResult::RESULT_ERROR);
                    $result->setMessage('Could not add custom fields.');

                    return $result;
                }

                /** @var EntityApplicationService $entityService */
                $entityService = $this->container->get('application.entity.service');

                $entityService->removeMissingEntitiesForCustomField($field);

                $customFieldRepository->beginTransaction();

                try {
                    if (!($customFieldId = $customFieldRepository->add($customField))) {
                        $customFieldRepository->rollback();
                        return $result;
                    }

                    $customField->setId(new Id($customFieldId));

                    $this->addCustomFieldServices($customField);
                    $this->addCustomFieldEvents($customField);
                    $this->addCustomFieldOptions($customField);
                } catch (QueryExecutionException $e) {
                    $customFieldRepository->rollback();
                    throw $e;
                }

                $customFieldRepository->commit();
            } else {
                /** @var array $customFieldOptionsArray */
                $customFieldOptionsArray = $field['options'];

                if (!($customField instanceof CustomField)) {
                    $result->setResult(CommandResult::RESULT_ERROR);
                    $result->setMessage('Could not update custom field.');

                    return $result;
                }

                $customFieldRepository->beginTransaction();

                try {
                    if (!$customFieldRepository->update($customField->getId()->getValue(), $customField)) {
                        $customFieldRepository->rollback();
                        return $result;
                    }

                    $customField = $this->updateCustomFieldOptions($customField, $customFieldOptionsArray);

                    $this->updateCustomFieldServices($customField);
                    $this->updateCustomFieldEvents($customField);
                } catch (QueryExecutionException $e) {
                    $customFieldRepository->rollback();
                    throw $e;
                }

                $customFieldRepository->commit();
            }
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully processed custom fields.');

        return $result;
    }

    /**
     * @param CustomField $customField
     *
     * @throws QueryExecutionException
     * @throws \Interop\Container\Exception\ContainerException
     */
    private function addCustomFieldServices($customField)
    {
        /** @var CustomFieldServiceRepository $customFieldServiceRepository */
        $customFieldServiceRepository = $this->container->get('domain.customFieldService.repository');

        /** @var Service $service */
        foreach ($customField->getServices()->getItems() as $service) {
            $customFieldServiceRepository->add($customField->getId()->getValue(), $service->getId()->getValue());
        }
    }

    /**
     * @param CustomField $customField
     *
     * @throws QueryExecutionException
     * @throws \Interop\Container\Exception\ContainerException
     */
    private function addCustomFieldEvents($customField)
    {
        /** @var CustomFieldEventRepository $customFieldEventRepository */
        $customFieldEventRepository = $this->container->get('domain.customFieldEvent.repository');

        /** @var Event $event */
        foreach ($customField->getEvents()->getItems() as $event) {
            $customFieldEventRepository->add($customField->getId()->getValue(), $event->getId()->getValue());
        }
    }

    /**
     * @param CustomField $customField
     *
     * @throws QueryExecutionException
     * @throws \Interop\Container\Exception\ContainerException
     */
    private function addCustomFieldOptions($customField)
    {
        /** @var CustomFieldOptionRepository $customFieldOptionRepository */
        $customFieldOptionRepository = $this->container->get('domain.customFieldOption.repository');

        /** @var CustomFieldOption $option */
        foreach ($customField->getOptions()->getItems() as $option) {
            $option->setCustomFieldId(new Id($customField->getId()->getValue()));
            $optionId = $customFieldOptionRepository->add($option);
            $option->setId(new Id($optionId));
        }
    }

    /**
     * @param CustomField $customField
     *
     * @throws ContainerValueNotFoundException
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    private function updateCustomFieldServices($customField)
    {
        /** @var CustomFieldServiceRepository $customFieldServiceRepository */
        $customFieldServiceRepository = $this->container->get('domain.customFieldService.repository');

        // Get services for this custom field from database
        $customFieldServices = $customFieldServiceRepository->getByCustomFieldId($customField->getId()->getValue());

        // Get ID's of saved services
        $customFieldServicesIds = array_column($customFieldServices, 'serviceId');

        /** @var Service $service */
        foreach ($customField->getServices()->getItems() as $service) {
            // Add only service that is not saved already.
            // Third parameter needs to be false, because some servers return ID's as string
            if (!in_array($service->getId()->getValue(), $customFieldServicesIds, false)) {
                $customFieldServiceRepository->add($customField->getId()->getValue(), $service->getId()->getValue());
                $customFieldServicesIds[] = $service->getId()->getValue();
            }
        }

        $frontedServicesIds = [];

        foreach ($customField->getServices()->toArray() as $service) {
            $frontedServicesIds[] = $service['id'];
        }

        foreach ($customFieldServicesIds as $customFieldServicesId) {
            // Remove services that are saved in the database, but not received from frontend
            // Third parameter needs to be false, because some servers return ID's as string
            if (!in_array($customFieldServicesId, $frontedServicesIds, false)) {
                $customFieldServiceRepository->deleteByCustomFieldIdAndServiceId(
                    $customField->getId()->getValue(),
                    $customFieldServicesId
                );
            }
        }
    }

    /**
     * @param CustomField $customField
     *
     * @throws ContainerValueNotFoundException
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    private function updateCustomFieldEvents($customField)
    {
        /** @var CustomFieldEventRepository $customFieldEventRepository */
        $customFieldEventRepository = $this->container->get('domain.customFieldEvent.repository');

        // Get events for this custom field from database
        $customFieldEvents = $customFieldEventRepository->getByCustomFieldId($customField->getId()->getValue());

        // Get ID's of saved event
        $customFieldEventsIds = array_column($customFieldEvents, 'eventId');

        /** @var Event $event */
        foreach ($customField->getEvents()->getItems() as $event) {
            // Add only event that is not saved already.
            // Third parameter needs to be false, because some servers return ID's as string
            if (!in_array($event->getId()->getValue(), $customFieldEventsIds, false)) {
                $customFieldEventRepository->add($customField->getId()->getValue(), $event->getId()->getValue());
                $customFieldEventsIds[] = $event->getId()->getValue();
            }
        }

        $frontedEventsIds = [];

        foreach ($customField->getEvents()->toArray() as $event) {
            $frontedEventsIds[] = $event['id'];
        }

        foreach ($customFieldEventsIds as $customFieldEventsId) {
            // Remove events that are saved in the database, but not received from frontend
            // Third parameter needs to be false, because some servers return ID's as string
            if (!in_array($customFieldEventsId, $frontedEventsIds, false)) {
                $customFieldEventRepository->deleteByCustomFieldIdAndEventId(
                    $customField->getId()->getValue(),
                    $customFieldEventsId
                );
            }
        }
    }

    /**
     * @param CustomField $customField
     * @param array       $customFieldOptionsArray
     *
     * @return CustomField
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    private function updateCustomFieldOptions($customField, $customFieldOptionsArray)
    {
        /** @var CustomFieldOptionRepository $customFieldOptionRepository */
        $customFieldOptionRepository = $this->container->get('domain.customFieldOption.repository');

        /** @var CustomFieldOption $customFieldOption */
        foreach ($customField->getOptions()->getItems() as $customFieldOptionKey => $customFieldOption) {
            $customFieldOptionArray = $customFieldOptionsArray[$customFieldOptionKey];

            if (!isset($customFieldOptionArray['customFieldId']) && $customField->getId()) {
                $customFieldOptionArray['customFieldId'] = $customField->getId()->getValue();
                $customFieldOption->setCustomFieldId($customField->getId());
            }

            if (!empty($customFieldOptionArray['new']) && empty($customFieldOptionArray['deleted'])) {
                $customFieldOptionId = $customFieldOptionRepository->add($customFieldOption);
                $customFieldOption->setId(new Id($customFieldOptionId));
            }

            if (!empty($customFieldOptionArray['deleted']) && empty($customFieldOptionArray['new'])) {
                $customFieldOptionRepository->delete($customFieldOption->getId()->getValue());
                $customField->getOptions()->deleteItem($customFieldOptionKey);
            }

            if (
                !empty($customFieldOptionArray['edited']) &&
                empty($customFieldOptionArray['deleted']) &&
                empty($customFieldOptionArray['new'])
            ) {
                $customFieldOptionRepository->update($customFieldOption->getId()->getValue(), $customFieldOption);
            }
        }

        return $customField;
    }
}
