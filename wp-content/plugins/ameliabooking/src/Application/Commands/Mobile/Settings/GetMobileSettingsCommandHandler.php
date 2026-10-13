<?php

namespace AmeliaBooking\Application\Commands\Mobile\Settings;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Common\Exceptions\AuthorizationException;
use AmeliaBooking\Domain\Services\Settings\SettingsService;

/**
 * Returns the payment-display settings the Amelia Staff mobile app needs to
 * format prices (currency symbol/code, position, decimals, separators).
 *
 * Authenticated via the provider JWT (same cabinet-provider path as other
 * /mobile/v1/* routes). Intentionally does NOT require SETTINGS permission —
 * every signed-in staff member needs the currency to display prices, and the
 * payload is non-sensitive display config only.
 *
 * @package AmeliaBooking\Application\Commands\Mobile\Settings
 */
class GetMobileSettingsCommandHandler extends CommandHandler
{
    /**
     * @param GetMobileSettingsCommand $command
     *
     * @return CommandResult
     */
    public function handle(GetMobileSettingsCommand $command)
    {
        $result = new CommandResult();

        try {
            $command->getUserApplicationService()->authorization(
                $command->getToken(),
                $command->getCabinetType()
            );
        } catch (AuthorizationException $e) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage('Could not retrieve user');
            $result->setData(['reauthorize' => true]);

            return $result;
        }

        /** @var SettingsService $settingsService */
        $settingsService = $this->getContainer()->get('domain.settings.service');
        $payments        = $settingsService->getCategorySettings('payments');

        // Raw payments settings store ISO code as `currency` and the display
        // glyph as `symbol` (see ActivationSettingsHook::getDefaultPaymentsSettings).
        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully retrieved mobile settings');
        $result->setData(
            [
                'payments' => [
                    'currencyCode'          => isset($payments['currency']) ? (string) $payments['currency'] : 'USD',
                    'symbol'                => isset($payments['symbol']) ? (string) $payments['symbol'] : '$',
                    'priceSymbolPosition'   => isset($payments['priceSymbolPosition'])
                        ? (string) $payments['priceSymbolPosition']
                        : 'before',
                    'priceNumberOfDecimals' => isset($payments['priceNumberOfDecimals'])
                        ? (int) $payments['priceNumberOfDecimals']
                        : 2,
                    'priceSeparator'        => isset($payments['priceSeparator'])
                        ? (int) $payments['priceSeparator']
                        : 1,
                ],
            ]
        );

        return $result;
    }
}
