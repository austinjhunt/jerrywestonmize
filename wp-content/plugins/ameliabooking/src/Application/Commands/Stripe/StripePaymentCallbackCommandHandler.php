<?php

namespace AmeliaBooking\Application\Commands\Stripe;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Entity\Cache\Cache;
use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Domain\ValueObjects\Json;
use AmeliaBooking\Infrastructure\Repository\Cache\CacheRepository;
use AmeliaBooking\Infrastructure\WP\Translations\FrontendStrings;
use Exception;

/**
 * Class StripePaymentCallbackCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Stripe
 */
class StripePaymentCallbackCommandHandler extends CommandHandler
{
    public $mandatoryFields = [
        'name',
        'returnUrl',
    ];

    /**
     * @param StripePaymentCallbackCommand $command
     *
     * @return CommandResult
     * @throws Exception
     */
    public function handle(StripePaymentCallbackCommand $command)
    {
        $this->checkMandatoryFields($command);

        $paymentIntentId = $command->getField('payment_intent') ?: $command->getField('paymentIntentId');

        if ($paymentIntentId) {
            try {
                $completeHandler = new CompleteStripePaymentIntentCommandHandler($this->container);
                $completeResult = $completeHandler->complete($command->getField('name'), $paymentIntentId);

                if ($completeResult->getResult() === CommandResult::RESULT_ERROR) {
                    error_log(
                        'Amelia Stripe: failed to complete PaymentIntent in callback: ' .
                        $completeResult->getMessage()
                    );

                    $errorData = $completeResult->getData();
                    if (!is_array($errorData)) {
                        $errorData = [];
                    }
                    if (empty($errorData['message']) && $completeResult->getMessage()) {
                        $errorData['message'] = $completeResult->getMessage();
                    }

                    $this->markCompletionFailed($command->getField('name'), $errorData);
                    $this->releasePendingReservation($command->getField('name'));
                }
            } catch (Exception $e) {
                error_log('Amelia Stripe: exception while completing PaymentIntent in callback: ' . $e->getMessage());

                $this->markCompletionFailed(
                    $command->getField('name'),
                    [
                        'message'           => FrontendStrings::getCommonStrings()['payment_error'],
                        'paymentSuccessful' => false,
                    ]
                );
                $this->releasePendingReservation($command->getField('name'));
            }
        }

        $result = new CommandResult();
        $result->setResult(CommandResult::RESULT_SUCCESS);

        $returnUrl = $this->getValidReturnUrl(rawurldecode($command->getField('returnUrl')));
        $hash = '';

        if (strpos($returnUrl, '#') !== false) {
            $parts = explode('#', $returnUrl, 2);
            $returnUrl = $parts[0];
            $hash = '#' . $parts[1];
        }

        $result->setUrl(
            $returnUrl .
            (strpos($returnUrl, '?') !== false ? '&' : '?') .
            'ameliaCache=' . rawurlencode((string)$command->getField('name')) .
            $hash
        );

        return $result;
    }

    private function releasePendingReservation($name)
    {
        try {
            $cancelHandler = new CancelStripePaymentIntentCommandHandler($this->container);
            $cancelCommand = new CancelStripePaymentIntentCommand([]);
            $cancelCommand->setField('name', $name);
            $cancelHandler->handle($cancelCommand);
        } catch (Exception $e) {
            error_log('Amelia Stripe: failed to release pending reservation in callback: ' . $e->getMessage());
        }
    }

    private function markCompletionFailed($name, $data)
    {
        try {
            $identifier = explode('_', $name);

            if (!isset($identifier[0], $identifier[1])) {
                return;
            }

            /** @var CacheRepository $cacheRepository */
            $cacheRepository = $this->container->get('domain.cache.repository');

            /** @var Cache $cache */
            $cache = $cacheRepository->getByIdAndName($identifier[0], $identifier[1]);

            if (!$cache || !$cache->getData()) {
                return;
            }

            $cacheData = json_decode($cache->getData()->getValue(), true);

            if (!is_array($cacheData)) {
                return;
            }

            $errorData = is_array($data) ? $data : [];
            $errorData['paymentSuccessful'] = false;

            if (empty($errorData['message'])) {
                $errorData['message'] = FrontendStrings::getCommonStrings()['payment_error'];
            }

            $cacheData['status'] = 'failed';
            $cacheData['data'] = $errorData;

            $cache->setData(new Json(json_encode($cacheData)));

            $cacheRepository->update($cache->getId()->getValue(), $cache);
        } catch (Exception $e) {
            error_log('Amelia Stripe: failed to mark PaymentIntent completion error: ' . $e->getMessage());
        }
    }

    private function getValidReturnUrl($returnUrl)
    {
        $returnUrl = trim((string)$returnUrl);
        $siteUrl = rtrim(AMELIA_SITE_URL, '/');

        if ($returnUrl === '') {
            return $siteUrl;
        }

        if (strpos($returnUrl, '/') === 0 && strpos($returnUrl, '//') !== 0) {
            return $siteUrl . $returnUrl;
        }

        $returnParts = parse_url($returnUrl);
        $siteParts = parse_url(AMELIA_SITE_URL);

        if (
            empty($returnParts['scheme']) ||
            empty($returnParts['host']) ||
            !in_array(strtolower($returnParts['scheme']), ['http', 'https'], true) ||
            strtolower($returnParts['host']) !== strtolower($siteParts['host']) ||
            (isset($returnParts['port']) ? (int)$returnParts['port'] : null) !==
            (isset($siteParts['port']) ? (int)$siteParts['port'] : null)
        ) {
            return $siteUrl;
        }

        return $returnUrl;
    }
}
