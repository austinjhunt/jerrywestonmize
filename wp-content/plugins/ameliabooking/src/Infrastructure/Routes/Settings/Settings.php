<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See COPYING.md for license details.
 */

namespace AmeliaBooking\Infrastructure\Routes\Settings;

use AmeliaBooking\Application\Controller\Settings\ExportDataTransferController;
use AmeliaBooking\Application\Controller\Settings\GetSettingsController;
use AmeliaBooking\Application\Controller\Settings\ProcessImportDataTransferController;
use AmeliaBooking\Application\Controller\Settings\StartImportDataTransferController;
use AmeliaBooking\Application\Controller\Settings\UpdateSettingsCategoriesController;
use AmeliaBooking\Application\Controller\Settings\UpdateSettingsController;
use Slim\App;

/**
 * Class Settings
 *
 * @package AmeliaBooking\Infrastructure\Routes\Settings
 */
class Settings
{
    /**
     * @param App $app
     */
    public static function routes(App $app)
    {
        $app->get('/settings', GetSettingsController::class);

        $app->post('/settings', UpdateSettingsController::class);

        $app->post('/settings/data-transfer/export', ExportDataTransferController::class);

        $app->post('/settings/data-transfer/import/start', StartImportDataTransferController::class);

        $app->post('/settings/data-transfer/import/process', ProcessImportDataTransferController::class);

        $app->post('/settings/categories', UpdateSettingsCategoriesController::class);
    }
}
