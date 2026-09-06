<?php

namespace AmeliaBooking\Application\Commands\Settings;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\Location\AbstractCurrentLocation;
use AmeliaBooking\Application\Services\Stash\StashApplicationService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\ForbiddenFileUploadException;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Services\Api\BasicApiService;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Services\Apple\AbstractAppleCalendarService;
use AmeliaBooking\Infrastructure\Licence\Licence;
use AmeliaBooking\Infrastructure\Licence\LicenceConstants;
use AmeliaBooking\Infrastructure\Services\LessonSpace\AbstractLessonSpaceService;
use AmeliaBooking\Infrastructure\Services\Outlook\OutlookCredentialsValidatorService;
use AmeliaBooking\Infrastructure\WP\Integrations\WooCommerce\WooCommerceService;
use AmeliaBooking\Infrastructure\WP\UserRoles\SuperAdminRoleService;
use AmeliaBooking\Infrastructure\WP\UserRoles\UserRoles;
use AmeliaVendor\Melograno\UsageTracker\Core\UsageTracker;
use Exception;
use Interop\Container\Exception\ContainerException;
use AmeliaVendor\Melograno\UsageTracker\Collectors\Plugin\AmeliaCollector;
use Slim\Exception\ContainerValueNotFoundException;

/**
 * Class UpdateSettingsCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Settings
 */
class UpdateSettingsCommandHandler extends CommandHandler
{
    /**
     * @param UpdateSettingsCommand $command
     *
     * @return CommandResult
     * @throws AccessDeniedException
     * @throws ContainerValueNotFoundException
     * @throws ContainerException
     * @throws Exception
     */
    public function handle(UpdateSettingsCommand $command)
    {
        $result = new CommandResult();

        if (!$this->getContainer()->getPermissionsService()->currentUserCanWrite(Entities::SETTINGS)) {
            /** @var AbstractUser $loggedInUser */
            $loggedInUser = $this->container->get('logged.in.user');

            if (
                !$loggedInUser || !(
                    $loggedInUser->getType() === AbstractUser::USER_ROLE_ADMIN ||
                    $loggedInUser->getType() === AbstractUser::USER_ROLE_MANAGER
                )
            ) {
                throw new AccessDeniedException('You are not allowed to write settings.');
            }
        }

        /** @var SettingsService $settingsService */
        $settingsService = $this->getContainer()->get('domain.settings.service');

        $superAdminService = new SuperAdminRoleService();

        /** @var AbstractCurrentLocation $locationService */
        $locationService = $this->getContainer()->get('application.currentLocation.service');

        $commandFields = $command->getFields();
        $activationWasSubmitted = array_key_exists('activation', $commandFields) && is_array($commandFields['activation']);
        $activationActiveWasSubmitted = $activationWasSubmitted && array_key_exists('active', $commandFields['activation']);
        $savedActivationSettings = $settingsService->getCategorySettings('activation');
        $settingsFields = $commandFields;
        unset($settingsFields['isSuperAdmin'], $settingsFields['isSuperAdminRoleAvailable'], $settingsFields['superAdminCount']);

        if (isset($settingsFields['activation']) && !$superAdminService->canAccessActivationSettings()) {
            unset($settingsFields['activation']);
        }

        if (
            WooCommerceService::isEnabled() &&
            $command->getField('payments') &&
            $command->getField('payments')['wc']['enabled']
        ) {
            $settingsFields['payments']['wc']['productId'] = WooCommerceService::getIdForExistingOrNewProduct(
                $settingsService->getCategorySettings('payments')['wc']['productId']
            );
        }


        if ($command->getField('general') && $command->getField('general')['customFieldsUploadsPath']) {
            $uploadPath = $command->getField('general')['customFieldsUploadsPath'];
            if ($uploadPath[0] !== '/') {
                throw new ForbiddenFileUploadException('Attachment upload path must be an absolute path, starting with a slash (/).');
            }

            !is_dir($uploadPath) && !mkdir($uploadPath, 0755, true);

            if (!is_writable($uploadPath) || !is_dir($uploadPath)) {
                throw new ForbiddenFileUploadException('Attachment upload path is not writable or does not exist.');
            }

            if (!file_exists("$uploadPath/index.html")) {
                file_put_contents("$uploadPath/index.html", '');
            }
        }

        if ($command->getField('sendAllCF') !== null) {
            $notificationsSettings = $settingsService->getCategorySettings('notifications');

            $settingsFields['notifications'] = $notificationsSettings;

            $settingsFields['notifications']['sendAllCF'] = $command->getField('sendAllCF');

            unset($settingsFields['sendAllCF']);
        }

        if ($command->getField('googleCalendar') !== null) {
            $googleSettings = $command->getField('googleCalendar');

            $settingsFields['googleCalendar'] = array_merge(
                $settingsService->getCategorySettings('googleCalendar'),
                $googleSettings
            );
        }

        if ($command->getField('outlookCalendar') !== null) {
            $outlookSettings = $command->getField('outlookCalendar');

            unset($outlookSettings['token']);

            $savedOutlookSettings = $settingsService->getCategorySettings('outlookCalendar');
            $validateResult          = OutlookCredentialsValidatorService::validateCredentials(
                $outlookSettings,
                $savedOutlookSettings
            );

            if ($validateResult instanceof CommandResult) {
                return $validateResult;
            }

            $settingsFields['outlookCalendar'] = $validateResult;
        }

        if ($command->getField('providerBadges') !== null) {
            $rolesSettings = $settingsService->getCategorySettings('roles');

            $settingsFields['roles'] = $rolesSettings;

            $settingsFields['roles']['providerBadges'] = $command->getField('providerBadges');

            unset($settingsFields['providerBadges']);
        }

        if (
            !$settingsService->getCategorySettings('activation')['stash'] &&
            isset($settingsFields['activation']) &&
            !empty($settingsFields['activation']['stash'])
        ) {
            /** @var StashApplicationService $stashApplicationService */
            $stashApplicationService = $this->container->get('application.stash.service');

            $stashApplicationService->setStash();
        }

        if (
            isset($settingsFields['daysOff']) &&
            $settingsService->getCategorySettings('activation')['stash'] &&
            $settingsService->getCategorySettings('daysOff') !== $settingsFields['daysOff'] &&
            $command->getField('daysOff') !== null
        ) {
            /** @var StashApplicationService $stashApplicationService */
            $stashApplicationService = $this->container->get('application.stash.service');

            $stashApplicationService->setStash($settingsFields['daysOff']);
        }

        $settingsFields['activation'] = array_merge(
            $settingsService->getCategorySettings('activation'),
            isset($settingsFields['activation']['deleteTables']) ? [
                'deleteTables' => $settingsFields['activation']['deleteTables'] ? true : false
            ] : [],
            isset($settingsFields['activation']['envatoTokenEmail']) ? [
                'envatoTokenEmail' => $settingsFields['activation']['envatoTokenEmail']
            ] : [],
            isset($settingsFields['activation']['active']) ? [
                'active' => $settingsFields['activation']['active']
            ] : [],
            isset($settingsFields['activation']['stash']) ? [
                'stash' => $settingsFields['activation']['stash']
            ] : [],
            isset($settingsFields['activation']['showAmeliaPromoCustomizePopup']) ? [
                'showAmeliaPromoCustomizePopup' => $settingsFields['activation']['showAmeliaPromoCustomizePopup']
            ] : [],
            isset($settingsFields['activation']['showAmeliaSurvey']) ? [
                'showAmeliaSurvey' => $settingsFields['activation']['showAmeliaSurvey']
            ] : [],
            isset($settingsFields['activation']['customUrl']) ? [
                'customUrl' => $settingsFields['activation']['customUrl']
            ] : [],
            isset($settingsFields['activation']['v3AsyncLoading']) ? [
                'v3AsyncLoading' => $settingsFields['activation']['v3AsyncLoading']
            ] : [],
            isset($settingsFields['activation']['v3RelativePath']) ? [
                'v3RelativePath' => $settingsFields['activation']['v3RelativePath']
            ] : [],
            isset($settingsFields['activation']['enableThriveItems']) ? [
                'enableThriveItems' => $settingsFields['activation']['enableThriveItems']
            ] : [],
            isset($settingsFields['activation']['responseErrorAsConflict']) ? [
                'responseErrorAsConflict' => $settingsFields['activation']['responseErrorAsConflict']
            ] : [],
            isset($settingsFields['activation']['disableUrlParams']) ? [
                'disableUrlParams' => $settingsFields['activation']['disableUrlParams']
            ] : [],
            isset($settingsFields['activation']['hideUnavailableFeatures']) ? [
                'hideUnavailableFeatures' => $settingsFields['activation']['hideUnavailableFeatures']
            ] : [],
            isset($settingsFields['activation']['hideTipsAndSuggestions']) ? [
                'hideTipsAndSuggestions' => $settingsFields['activation']['hideTipsAndSuggestions']
            ] : [],
            isset($settingsFields['activation']['licence']) ? [
                'licence' => $settingsFields['activation']['licence']
            ] : [],
            isset($settingsFields['activation']['premiumBannerVisibility']) ? [
                'premiumBannerVisibility' => $settingsFields['activation']['premiumBannerVisibility']
            ] : [],
            isset($settingsFields['activation']['dismissibleBannerVisibility']) ? [
                'dismissibleBannerVisibility' => $settingsFields['activation']['dismissibleBannerVisibility']
            ] : []
        );

        if (
            $activationActiveWasSubmitted &&
            empty($savedActivationSettings['active']) &&
            !empty($settingsFields['activation']['active']) &&
            empty($savedActivationSettings['licenseActivatorUserId']) &&
            get_current_user_id() &&
            (current_user_can('manage_options') || is_super_admin())
        ) {
            $currentUserId = get_current_user_id();

            // Persist licence first so isAvailable()/grant() see the new Elite state,
            // then sync role registration before assigning SuperAdmin.
            if (array_key_exists('licence', $settingsFields['activation'])) {
                $settingsService->setSetting(
                    'activation',
                    'licence',
                    $settingsFields['activation']['licence']
                );
            }

            UserRoles::syncSuperAdminRoleAvailability();

            if (!$superAdminService->grant($currentUserId)) {
                $settingsService->setCategorySettings('activation', $savedActivationSettings);
                UserRoles::syncSuperAdminRoleAvailability();

                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage('Failed to assign Superadmin to the activating user.');
                $result->setData([]);

                return $result;
            }

            $settingsFields['activation']['licenseActivatorUserId'] = $currentUserId;
        }

        if ($command->getField('usedLanguages') !== null) {
            $generalSettings = $settingsService->getCategorySettings('general');

            $settingsFields['general'] = $generalSettings;

            $settingsFields['general']['usedLanguages'] = $command->getField('usedLanguages');

            unset($settingsFields['usedLanguages']);
        }

        if ($command->getField('lessonSpace') !== null && $settingsFields['lessonSpace']['apiKey']) {
            if (!$settingsService->getCategorySettings('lessonSpace')['companyId']) {
                /** @var AbstractLessonSpaceService $lessonSpaceService */
                $lessonSpaceService = $this->container->get('infrastructure.lesson.space.service');

                $companyDetails = $lessonSpaceService->getCompanyId($settingsFields['lessonSpace']['apiKey']);

                $settingsFields['lessonSpace']['companyId'] = $companyDetails['id'];
            } else {
                $settingsFields['lessonSpace']['companyId'] = $settingsService->getCategorySettings('lessonSpace')['companyId'];
            }
        }

        if ($command->getField('payments') && !empty($command->getField('payments')['square'])) {
            $settingsFields['payments']['square']['accessToken'] = $settingsService->getCategorySettings('payments')['square']['accessToken'];
        }

        if (isset($settingsFields['apiKeys']) && isset($settingsFields['apiKeys']['apiKeys'])) {
            /** @var BasicApiService $apiService */
            $apiService = $this->getContainer()->get('domain.api.service');
            foreach ($settingsFields['apiKeys']['apiKeys'] as $index => $apiKey) {
                if (!empty($apiKey['isNew'])) {
                    $settingsFields['apiKeys']['apiKeys'][$index]['key'] = $apiService->createHash($settingsFields['apiKeys']['apiKeys'][$index]['key']);
                }
                unset($settingsFields['apiKeys']['apiKeys'][$index]['isNew']);
            }
        }

        if (isset($settingsFields['pageColumnSettings'])) {
            $currentPageColumnSettings = $settingsService->getCategorySettings('pageColumnSettings');

            foreach ($settingsFields['pageColumnSettings'] as $page => $columns) {
                $currentPageColumnSettings[$page] = $columns;
            }

            $settingsFields['pageColumnSettings'] = $currentPageColumnSettings;
        }

        if ($command->getField('appleCalendar') !== null) {
            $appleResult = $this->handleAppleCalendarSettings(
                $command->getField('appleCalendar'),
                $settingsService->getCategorySettings('appleCalendar')
            );

            if ($appleResult instanceof CommandResult) {
                return $appleResult;
            }

            $settingsFields['appleCalendar'] = $appleResult;
        }

        if (!empty($command->getField('customizedData'))) {
            $passedCustomizedData = $command->getField('customizedData');
            $customizedData       = $settingsService->getCategorySettings('customizedData');

            foreach ($passedCustomizedData as $key => $value) {
                $customizedData[$key] = $value;
            }

            $settingsFields['customizedData'] = $customizedData;
        }

        if ($command->getField('whiteLabel') !== null) {
            $savedWhiteLabelSettings = $settingsService->getCategorySettings('whiteLabel');

            // Older installs may lack the whiteLabel category; create defaults so the
            // unchanged-payload check below does not treat empty frontend values as a change.
            if (!is_array($savedWhiteLabelSettings)) {
                $savedWhiteLabelSettings = [
                    'pluginName'        => '',
                    'hideExternalLinks' => false,
                    'pictureFullPath'   => '',
                    'pictureThumbPath'  => '',
                ];

                $settingsService->setCategorySettings('whiteLabel', $savedWhiteLabelSettings);
            }

            $canManageWhiteLabel = $superAdminService->isCurrentUserSuperAdmin() &&
                Licence::getLicence() === LicenceConstants::DEVELOPER;

            if (!$canManageWhiteLabel) {
                $submittedWhiteLabelSettings = array_replace(
                    $savedWhiteLabelSettings,
                    $settingsFields['whiteLabel']
                );

                // The settings page always submits the full payload, so ignore an unchanged
                // whiteLabel category and only reject actual modification attempts
                if ($submittedWhiteLabelSettings != $savedWhiteLabelSettings) {
                    if (!$superAdminService->isCurrentUserSuperAdmin()) {
                        throw new AccessDeniedException('Only SuperAdmins can update white label settings.');
                    }

                    throw new AccessDeniedException('White label settings are only available on the Developer licence.');
                }

                unset($settingsFields['whiteLabel']);
            }
        }

        if (isset($settingsFields['general'])) {
            $armUsageTrackingNoticeOnDisable = (bool) $command->getField('armUsageTrackingNoticeOnDisable');
            UsageTracker::updateSettings(
                $settingsFields['general'],
                new AmeliaCollector(),
                $armUsageTrackingNoticeOnDisable
            );
        }

        $settingsFields = apply_filters('amelia_before_settings_updated_filter', $settingsFields);

        do_action('amelia_before_settings_updated', $settingsFields);

        $settingsService->setAllSettings($settingsFields);

        if (
            array_key_exists('whiteLabel', $settingsFields) ||
            array_key_exists('featuresIntegrations', $settingsFields)
        ) {
            UserRoles::syncRoleLabels();
        }

        if (array_key_exists('activation', $settingsFields)) {
            UserRoles::syncSuperAdminRoleAvailability();
        }

        $settings = $settingsService->getAllSettingsCategorized();
        $settings['isSuperAdmin'] = $superAdminService->isCurrentUserSuperAdmin();
        $settings['isSuperAdminRoleAvailable'] = SuperAdminRoleService::isAvailable();
        $settings['superAdminCount'] = $superAdminService->countSuperAdmins();
        if (!$superAdminService->canAccessActivationSettings()) {
            $settings['activation']['purchaseCodeStore'] = null;
            $settings['activation']['envatoTokenEmail'] = '';
            $settings['activation']['licence'] = null;
            $settings['activation']['licenseActivatorUserId'] = null;
        }

        $settings['general']['phoneDefaultCountryCode'] = $settings['general']['phoneDefaultCountryCode'] === 'auto' ?
            $locationService->getCurrentLocationCountryIso($settings['general']['ipLocateApiKey']) : $settings['general']['phoneDefaultCountryCode'];

        $settings['general'] = array_merge($settings['general'], UsageTracker::getSettings(new AmeliaCollector()));

        do_action('amelia_after_settings_updated', $settingsFields);

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully updated settings.');
        $result->setData(
            [
                'settings' => $settings
            ]
        );

        return $result;
    }

    /**
     * Validates and merges incoming Apple Calendar settings with the saved ones.
     *
     * @param array $appleSettings
     * @param array $savedAppleSettings
     *
     * @return CommandResult|array
     */
    private function handleAppleCalendarSettings(array $appleSettings, array $savedAppleSettings)
    {
        $clientIdProvided     = array_key_exists('clientID', $appleSettings);
        $clientSecretProvided = array_key_exists('clientSecret', $appleSettings);

        if ($clientIdProvided || $clientSecretProvided) {
            $incomingClientId     = $clientIdProvided ? (string)($appleSettings['clientID'] ?? '') : '';
            $incomingClientSecret = $clientSecretProvided ? (string)($appleSettings['clientSecret'] ?? '') : '';

            $savedClientId     = !empty($savedAppleSettings['clientID']) ? $savedAppleSettings['clientID'] : '';
            $savedClientSecret = !empty($savedAppleSettings['clientSecret']) ? $savedAppleSettings['clientSecret'] : '';

            $hasClientId     = $clientIdProvided && $incomingClientId !== '';
            $hasClientSecret = $clientSecretProvided && $incomingClientSecret !== '';

            if ($hasClientId !== $hasClientSecret) {
                $result = new CommandResult();
                $result->setDataInResponse(true);
                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage('Both iCloud email address and app-specific password are required.');

                return $result;
            }

            if ($hasClientId && $hasClientSecret) {
                $credentialsChanged = ($incomingClientId !== $savedClientId) || ($incomingClientSecret !== $savedClientSecret);

                if ($credentialsChanged) {
                    /** @var AbstractAppleCalendarService $appleCalendarService */
                    $appleCalendarService = $this->container->get('infrastructure.apple.calendar.service');

                    if (!$appleCalendarService->handleAppleCredentials($incomingClientId, $incomingClientSecret)) {
                        $result = new CommandResult();
                        $result->setDataInResponse(true);
                        $result->setResult(CommandResult::RESULT_ERROR);
                        $result->setMessage('Make sure you are using the correct iCloud email address and app-specific password.');

                        return $result;
                    }
                }
            }

            if (!$hasClientId && !$hasClientSecret && (!empty($savedClientId) || !empty($savedClientSecret))) {
                $providerRepository = $this->container->get('domain.users.providers.repository');
                /** @var Collection $providers */
                $providers = $providerRepository->getAll();
                foreach ($providers->getItems() as $provider) {
                    $providerRepository->updateFieldById($provider->getId()->getValue(), null, 'employeeAppleCalendar');
                    $providerRepository->updateFieldById($provider->getId()->getValue(), null, 'appleCalendarId');
                }
            }
        }

        $merged = $savedAppleSettings;
        foreach ($appleSettings as $key => $value) {
            $merged[$key] = $value;
        }

        return $merged;
    }
}
