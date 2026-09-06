<?php

namespace AmeliaBooking\Application\Commands\Stripe;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Services\Payment\PaymentApplicationService;
use AmeliaBooking\Domain\Entity\Cache\Cache;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\ValueObjects\Json;
use AmeliaBooking\Infrastructure\Repository\Cache\CacheRepository;
use AmeliaBooking\Infrastructure\Services\Payment\StripeService;

/**
 * Class CancelStripePaymentIntentCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Stripe
 */
class CancelStripePaymentIntentCommandHandler extends CommandHandler
{
    public $mandatoryFields = [
        'name',
    ];

    /**
     * @param CancelStripePaymentIntentCommand $command
     *
     * @return CommandResult
     */
    public function handle(CancelStripePaymentIntentCommand $command)
    {
        $result = new CommandResult();

        $this->checkMandatoryFields($command);

        /** @var PaymentApplicationService $paymentAS */
        $paymentAS = $this->container->get('application.payment.service');

        /** @var StripeService $stripeService */
        $stripeService = $this->container->get('infrastructure.payment.stripe.service');

        /** @var CacheRepository $cacheRepository */
        $cacheRepository = $this->container->get('domain.cache.repository');

        $name = (string)$command->getField('name');
        $data = explode('_', $name);
        $requestedType = isset($data[2]) ? $data[2] : null;

        if (
            count($data) < 3 ||
            !in_array($requestedType, [Entities::APPOINTMENT, Entities::EVENT, Entities::PACKAGE], true)
        ) {
            $result->setResult(CommandResult::RESULT_SUCCESS);

            return $result;
        }

        /** @var Cache|null $cache */
        $cache = isset($data[0], $data[1]) ? $cacheRepository->getByIdAndName($data[0], $data[1]) : null;

        if (!$cache || !$cache->getData()) {
            $result->setResult(CommandResult::RESULT_SUCCESS);

            return $result;
        }

        $cacheData = json_decode($cache->getData()->getValue(), true);

        if (empty($cacheData['paymentMethod']) || $cacheData['paymentMethod'] !== 'stripe') {
            $result->setResult(CommandResult::RESULT_SUCCESS);

            return $result;
        }

        if (!empty($cacheData['status']) && $cacheData['status'] === 'paid') {
            $result->setResult(CommandResult::RESULT_SUCCESS);

            return $result;
        }

        $paymentIntentId = !empty($cacheData['stripePaymentIntentId']) ?
            (string)$cacheData['stripePaymentIntentId'] : '';

        if ($paymentIntentId) {
            $transfers = !empty($cacheData['stripeTransfers']) ? $cacheData['stripeTransfers'] : [];
            $stripeService->cancelPaymentIntent($paymentIntentId, $transfers);
        }

        if (!$cache->getPaymentId()) {
            // Keep an already recorded failure so restore shows the right error after redirect.
            if (empty($cacheData['status']) || $cacheData['status'] !== 'failed') {
                $cacheData['status'] = 'canceled';
            }
            $cache->setData(new Json(json_encode($cacheData)));
            $cacheRepository->update($cache->getId()->getValue(), $cache);

            $result->setResult(CommandResult::RESULT_SUCCESS);

            return $result;
        }

        $cachedType = $this->getCachedType($cacheData, $requestedType);

        if (!$cachedType) {
            $result->setResult(CommandResult::RESULT_SUCCESS);

            return $result;
        }

        return $paymentAS->updateAppointmentAndCache($cachedType, 'canceled', $cache, $paymentIntentId);
    }

    /**
     * @param array|null $cacheData
     * @param string     $requestedType
     *
     * @return string|null
     */
    private function getCachedType($cacheData, $requestedType)
    {
        if (!is_array($cacheData)) {
            return in_array($requestedType, [Entities::APPOINTMENT, Entities::EVENT, Entities::PACKAGE], true) ?
                $requestedType : null;
        }

        $cachedType = !empty($cacheData['type']) ? $cacheData['type'] : null;

        if (!$cachedType && !empty($cacheData['request']['type'])) {
            $cachedType = $cacheData['request']['type'];
        }

        if (!$cachedType && !empty($cacheData['request']['state']['appointment']['type'])) {
            $cachedType = $cacheData['request']['state']['appointment']['type'];
        }

        if (!$cachedType && !empty($cacheData['response']['type'])) {
            $cachedType = $cacheData['response']['type'];
        }

        if (!$cachedType) {
            $cachedType = $requestedType;
        }

        return in_array($cachedType, [Entities::APPOINTMENT, Entities::EVENT, Entities::PACKAGE], true) ?
            $cachedType : null;
    }
}
