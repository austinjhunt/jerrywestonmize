<?php

namespace AmeliaBooking\Infrastructure\Services\DataTransfer;

use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Connection;
use AmeliaBooking\Infrastructure\WP\InstallActions\DB;
use AmeliaBooking\Infrastructure\WP\InstallActions\DB\Gallery\GalleriesTable;
use AmeliaBooking\Infrastructure\WP\InstallActions\DB\User\UsersTable;
use AmeliaBooking\Infrastructure\WP\UserService\CreateWPUser;
use RuntimeException;
use AmeliaVendor\Psr\Http\Message\UploadedFileInterface as UploadedFile;
use ZipArchive;

/**
 * Class DataTransferService
 *
 * @package AmeliaBooking\Infrastructure\Services\DataTransfer
 */
class DataTransferService
{
    private const JOB_OPTION_PREFIX = 'amelia_data_transfer_job_';

    private const FORMAT_VERSION = 1;

    /** Rows read and inserted per API call */
    private const IMPORT_ROWS_PER_API_CALL = 1000;

    /** Rows per multi-row INSERT SQL statement. */
    private const IMPORT_INSERT_ROWS = 100;

    /** @var Container */
    private $container;

    /** @var Connection */
    private $connection;

    /** @var SettingsService */
    private $settingsService;

    /** @var array<string, array<int, string>> */
    private $columnCache = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->connection = $container->getDatabaseConnection();
        $this->settingsService = $container->get('domain.settings.service');
    }

    public function exportArchive(): array
    {
        $this->assertZipArchiveAvailable();

        $jobId = wp_generate_uuid4();
        $workingDirectory = $this->createWorkingDirectory($jobId);
        $archivePath = $workingDirectory . '/amelia-export.ameliafile';

        $zip = new ZipArchive();

        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->deleteDirectory($workingDirectory);

            throw new RuntimeException('Unable to create the Amelia export archive.');
        }

        $metadata = [
            'formatVersion' => self::FORMAT_VERSION,
            'pluginVersion' => defined('AMELIA_VERSION') ? AMELIA_VERSION : null,
            'exportedAt' => gmdate('c'),
            'exportedEntities' => [
                'settings',
                'locations',
                'customers',
                'employees',
                'categories',
                'services',
                'extras',
                'packages',
                'resources',
                'notifications',
                'appointments',
                'events',
                'payments',
                'coupons',
                'taxes',
                'customFields',
            ],
            'datasets' => [],
        ];

        $settingsPath = 'settings/settings.json';
        $sanitizedSettings = $this->getSanitizedSettings();
        $zip->addFromString($settingsPath, json_encode(['data' => $sanitizedSettings]));
        $metadata['datasets'][] = [
            'key' => 'settings',
            'path' => $settingsPath,
            'records' => 1,
        ];

        foreach ($this->getDatasetDefinitions() as $dataset) {
            $rows = $this->getDatasetRows($dataset);
            $relativePath = $dataset['group'] . '/' . $dataset['file'];

            $zip->addFromString($relativePath, json_encode(['data' => $rows]));

            $metadata['datasets'][] = [
                'key' => $dataset['key'],
                'path' => $relativePath,
                'records' => count($rows),
            ];
        }

        $zip->addFromString('metadata.json', json_encode($metadata));
        $zip->close();

        $content = file_get_contents($archivePath);

        $this->deleteDirectory($workingDirectory);

        if ($content === false) {
            throw new RuntimeException('Unable to read the Amelia export archive.');
        }

        return [
            'fileName' => 'amelia-export-' . gmdate('Ymd-His') . '.ameliafile',
            'mimeType' => 'application/octet-stream',
            'archive' => base64_encode($content),
            'metadata' => $metadata,
        ];
    }

    public function startImportJob(UploadedFile $file): array
    {
        $this->assertZipArchiveAvailable();

        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Unable to upload the Amelia archive.');
        }

        $clientFilename = $file->getClientFilename() ?: 'amelia-export.ameliafile';

        if (!$this->hasAmeliaFileExtension($clientFilename)) {
            throw new RuntimeException('The uploaded file must use the .ameliafile extension.');
        }

        $jobId = wp_generate_uuid4();
        $workingDirectory = $this->createWorkingDirectory($jobId);
        $archivePath = $workingDirectory . '/import.ameliafile';
        $extractDirectory = $workingDirectory . '/extract';

        wp_mkdir_p($extractDirectory);
        $file->moveTo($archivePath);

        $metadata = $this->extractAndValidateArchive($archivePath, $extractDirectory);
        $steps = $this->buildImportSteps($metadata, $extractDirectory);

        $job = [
            'id' => $jobId,
            'status' => 'pending',
            'archivePath' => $archivePath,
            'extractDirectory' => $extractDirectory,
            'createdAt' => gmdate('c'),
            'updatedAt' => gmdate('c'),
            'currentStep' => 0,
            'processedRows' => 0,
            'totalRows' => array_sum(array_column($steps, 'records')) ?: 1,
            'tablesTruncated' => false,
            'steps' => $steps,
            'summary' => [
                'inserted' => 0,
                'skipped' => 0,
            ],
            'errors' => [],
            'metadata' => $metadata,
        ];

        $this->saveJob($job);

        return $this->formatJobStatus($job);
    }

    public function processImportJob(string $jobId): array
    {
        $job = $this->getJob($jobId);

        if (!$job) {
            throw new RuntimeException('The Amelia import job could not be found.');
        }

        if (in_array($job['status'], ['completed', 'failed'], true)) {
            return $this->formatJobStatus($job);
        }

        $this->normalizeJobStepRecordCounts($job);

        $job['status'] = 'processing';
        $job['updatedAt'] = gmdate('c');

        try {
            if (empty($job['tablesTruncated'])) {
                $this->truncateAllTables();
                $job['tablesTruncated'] = true;
            }

            $idx = (int) $job['currentStep'];

            if (!isset($job['steps'][$idx])) {
                $job['status'] = 'completed';
                $job['updatedAt'] = gmdate('c');
                $this->cleanupJobFiles($job);

                return $this->formatJobStatus($job);
            }

            $step = $job['steps'][$idx];
            $rowBudget = self::IMPORT_ROWS_PER_API_CALL;

            while ($rowBudget > 0 && isset($job['steps'][$idx])) {
                $step = $job['steps'][$idx];

                if (($step['key'] ?? '') === 'settings') {
                    $settings = $this->readJsonData($job['extractDirectory'] . '/' . $step['path']);
                    $this->importSettings($settings);
                    $job['processedRows'] += 1;
                    $job['steps'][$idx]['processed'] = 1;
                    $idx++;
                    $job['currentStep'] = $idx;
                    $rowBudget--;

                    continue;
                }

                $rows = $this->readJsonData($job['extractDirectory'] . '/' . $step['path']);
                $actualRecords = count($rows);
                $configuredRecords = (int) ($job['steps'][$idx]['records'] ?? 0);

                // Self-heal legacy jobs whose step totals were initialized incorrectly.
                if ($configuredRecords !== $actualRecords) {
                    $job['steps'][$idx]['records'] = $actualRecords;
                    $job['totalRows'] = max(1, ((int) $job['totalRows']) - $configuredRecords + $actualRecords);
                }

                $offset = (int) $job['steps'][$idx]['processed'];
                $records = (int) $job['steps'][$idx]['records'];
                $remainingInStep = $records - $offset;

                if ($remainingInStep <= 0) {
                    $idx++;
                    $job['currentStep'] = $idx;

                    continue;
                }

                $take = min($rowBudget, $remainingInStep);
                $chunk = array_slice($rows, $offset, $take);

                $this->importDatasetRows($step, $chunk, $job);

                $processedCount = count($chunk);
                $job['processedRows'] += $processedCount;
                $job['steps'][$idx]['processed'] = $offset + $processedCount;
                $rowBudget -= $processedCount;

                if ($job['steps'][$idx]['processed'] >= $job['steps'][$idx]['records']) {
                    $idx++;
                    $job['currentStep'] = $idx;
                }
            }

            if ($job['currentStep'] >= count($job['steps'])) {
                $job['status'] = 'completed';
                $this->cleanupJobFiles($job);
            }
        } catch (\Exception $e) {
            $this->appendImportError(
                $job,
                sprintf('Dataset %s: %s', $step['key'] ?? 'unknown', $e->getMessage())
            );
            $job['status'] = 'failed';
            $this->cleanupJobFiles($job, false);
        }

        $job['updatedAt'] = gmdate('c');

        if ($job['status'] !== 'completed') {
            $this->saveJob($job);
        }

        return $this->formatJobStatus($job);
    }

    private function normalizeJobStepRecordCounts(array &$job): void
    {
        if (empty($job['extractDirectory']) || !is_dir($job['extractDirectory']) || empty($job['steps'])) {
            return;
        }

        foreach ($job['steps'] as $index => $step) {
            if (($step['key'] ?? '') === 'settings') {
                $job['steps'][$index]['records'] = 1;
                continue;
            }

            if (empty($step['path'])) {
                continue;
            }

            $path = $job['extractDirectory'] . '/' . ltrim((string) $step['path'], '/');

            if (!file_exists($path)) {
                continue;
            }

            $job['steps'][$index]['records'] = count($this->readJsonData($path));
        }

        $job['totalRows'] = max(1, array_sum(array_map(function ($step) {
            return (int) ($step['records'] ?? 0);
        }, $job['steps'])));
    }

    private function getDatasetDefinitions(): array
    {
        return [
            ['key' => 'locations', 'group' => 'locations', 'file' => 'locations.json',
                'tableClass' => DB\Location\LocationsTable::class],
            ['key' => 'customers', 'group' => 'users', 'file' => 'customers.json', 'type' => 'users',
                'userType' => 'customer', 'tableClass' => DB\User\UsersTable::class],
            ['key' => 'employees', 'group' => 'users', 'file' => 'employees.json', 'type' => 'users',
                'userType' => 'provider', 'tableClass' => DB\User\UsersTable::class],
            ['key' => 'providersOutlookCalendar', 'group' => 'schedules', 'file' => 'providers-outlook-calendar.json',
                'tableClass' => DB\User\Provider\ProvidersOutlookCalendarTable::class],
            ['key' => 'providersGoogleCalendar', 'group' => 'schedules', 'file' => 'providers-google-calendar.json',
                'tableClass' => DB\User\Provider\ProvidersGoogleCalendarTable::class],
            ['key' => 'providersLocations', 'group' => 'schedules', 'file' => 'providers-locations.json',
                'tableClass' => DB\User\Provider\ProvidersLocationTable::class],
            ['key' => 'providersWeekDays', 'group' => 'schedules', 'file' => 'providers-week-days.json',
                'tableClass' => DB\User\Provider\ProvidersWeekDayTable::class],
            ['key' => 'providersDaysOff', 'group' => 'schedules', 'file' => 'providers-days-off.json',
                'tableClass' => DB\User\Provider\ProvidersDayOffTable::class],
            ['key' => 'providersSpecialDays', 'group' => 'schedules', 'file' => 'providers-special-days.json',
                'tableClass' => DB\User\Provider\ProvidersSpecialDayTable::class],
            ['key' => 'providersTimeOut', 'group' => 'schedules', 'file' => 'providers-time-out.json',
                'tableClass' => DB\User\Provider\ProvidersTimeOutTable::class],
            ['key' => 'providersPeriods', 'group' => 'schedules', 'file' => 'providers-periods.json',
                'tableClass' => DB\User\Provider\ProvidersPeriodTable::class],
            ['key' => 'providersSpecialDayPeriods', 'group' => 'schedules', 'file' => 'providers-special-day-periods.json',
                'tableClass' => DB\User\Provider\ProvidersSpecialDayPeriodTable::class],
            ['key' => 'categories', 'group' => 'catalog', 'file' => 'categories.json',
                'tableClass' => DB\Bookable\CategoriesTable::class],
            ['key' => 'services', 'group' => 'catalog', 'file' => 'services.json',
                'tableClass' => DB\Bookable\ServicesTable::class],
            ['key' => 'notifications', 'group' => 'notifications', 'file' => 'notifications.json',
                'tableClass' => DB\Notification\NotificationsTable::class],
            ['key' => 'notificationsToEntities', 'group' => 'notifications', 'file' => 'notifications-to-entities.json',
                'tableClass' => DB\Notification\NotificationsToEntitiesTable::class],
            ['key' => 'providersPeriodServices', 'group' => 'catalog', 'file' => 'providers-period-services.json',
                'tableClass' => DB\User\Provider\ProvidersPeriodServiceTable::class],
            ['key' => 'providersPeriodLocations', 'group' => 'catalog', 'file' => 'providers-period-locations.json',
                'tableClass' => DB\User\Provider\ProvidersPeriodLocationTable::class],
            ['key' => 'providersSpecialDayPeriodServices', 'group' => 'catalog', 'file' => 'providers-special-day-period-services.json',
                'tableClass' => DB\User\Provider\ProvidersSpecialDayPeriodServiceTable::class],
            ['key' => 'providersSpecialDayPeriodLocations', 'group' => 'catalog', 'file' => 'providers-special-day-period-locations.json',
                'tableClass' => DB\User\Provider\ProvidersSpecialDayPeriodLocationTable::class],
            ['key' => 'providersServices', 'group' => 'catalog', 'file' => 'providers-services.json',
                'tableClass' => DB\User\Provider\ProvidersServiceTable::class],
            ['key' => 'extras', 'group' => 'catalog', 'file' => 'extras.json',
                'tableClass' => DB\Bookable\ExtrasTable::class],
            ['key' => 'packages', 'group' => 'catalog', 'file' => 'packages.json',
                'tableClass' => DB\Bookable\PackagesTable::class],
            ['key' => 'coupons', 'group' => 'finance', 'file' => 'coupons.json',
                'tableClass' => DB\Coupon\CouponsTable::class],
            ['key' => 'couponsToPackages', 'group' => 'finance', 'file' => 'coupons-to-packages.json',
                'tableClass' => DB\Coupon\CouponsToPackagesTable::class],
            ['key' => 'packageServices', 'group' => 'catalog', 'file' => 'package-services.json',
                'tableClass' => DB\Bookable\PackagesServicesTable::class],
            ['key' => 'packageServicesProviders', 'group' => 'catalog', 'file' => 'package-services-providers.json',
                'tableClass' => DB\Bookable\PackagesServicesProvidersTable::class],
            ['key' => 'packageServicesLocations', 'group' => 'catalog', 'file' => 'package-services-locations.json',
                'tableClass' => DB\Bookable\PackagesServicesLocationsTable::class],
            ['key' => 'packageCustomers', 'group' => 'bookings', 'file' => 'package-customers.json',
                'tableClass' => DB\Bookable\PackagesCustomersTable::class],
            ['key' => 'packageCustomersServices', 'group' => 'bookings', 'file' => 'package-customers-services.json',
                'tableClass' => DB\Bookable\PackagesCustomersServicesTable::class],
            ['key' => 'resources', 'group' => 'catalog', 'file' => 'resources.json',
                'tableClass' => DB\Bookable\ResourcesTable::class],
            ['key' => 'resourcesToEntities', 'group' => 'catalog', 'file' => 'resources-to-entities.json',
                'tableClass' => DB\Bookable\ResourcesToEntitiesTable::class],
            ['key' => 'appointments', 'group' => 'bookings', 'file' => 'appointments.json',
                'tableClass' => DB\Booking\AppointmentsTable::class],
            ['key' => 'events', 'group' => 'events', 'file' => 'events.json',
                'tableClass' => DB\Booking\EventsTable::class],
            ['key' => 'eventsTags', 'group' => 'events', 'file' => 'event-tags.json',
                'tableClass' => DB\Booking\EventsTagsTable::class],
            ['key' => 'eventsTickets', 'group' => 'events', 'file' => 'event-tickets.json',
                'tableClass' => DB\Booking\EventsTicketsTable::class],
            ['key' => 'eventsPeriods', 'group' => 'events', 'file' => 'event-periods.json',
                'tableClass' => DB\Booking\EventsPeriodsTable::class],
            ['key' => 'eventsProviders', 'group' => 'events', 'file' => 'event-providers.json',
                'tableClass' => DB\Booking\EventsProvidersTable::class],
            ['key' => 'customerBookings', 'group' => 'bookings', 'file' => 'customer-bookings.json',
                'tableClass' => DB\Booking\CustomerBookingsTable::class],
            ['key' => 'customerBookingsToExtras', 'group' => 'bookings', 'file' => 'customer-bookings-to-extras.json',
                'tableClass' => DB\Booking\CustomerBookingsToExtrasTable::class],
            ['key' => 'customerBookingsToEventsPeriods', 'group' => 'events', 'file' => 'customer-bookings-to-event-periods.json',
                'tableClass' => DB\Booking\CustomerBookingsToEventsPeriodsTable::class],
            ['key' => 'customerBookingsToEventsTickets', 'group' => 'events', 'file' => 'customer-bookings-to-event-tickets.json',
                'tableClass' => DB\Booking\CustomerBookingToEventsTicketsTable::class],
            ['key' => 'notificationsLog', 'group' => 'notifications', 'file' => 'notifications-log.json',
                'tableClass' => DB\Notification\NotificationsLogTable::class],
            ['key' => 'notificationsSmsHistory', 'group' => 'notifications', 'file' => 'notifications-sms-history.json',
                'tableClass' => DB\Notification\NotificationsSMSHistoryTable::class],
            ['key' => 'payments', 'group' => 'finance', 'file' => 'payments.json',
                'tableClass' => DB\Payment\PaymentsTable::class],
            ['key' => 'couponsToServices', 'group' => 'finance', 'file' => 'coupons-to-services.json',
                'tableClass' => DB\Coupon\CouponsToServicesTable::class],
            ['key' => 'couponsToEvents', 'group' => 'finance', 'file' => 'coupons-to-events.json',
                'tableClass' => DB\Coupon\CouponsToEventsTable::class],
            ['key' => 'customFields', 'group' => 'custom-fields', 'file' => 'custom-fields.json',
                'tableClass' => DB\CustomField\CustomFieldsTable::class],
            ['key' => 'customFieldsOptions', 'group' => 'custom-fields', 'file' => 'custom-field-options.json',
                'tableClass' => DB\CustomField\CustomFieldsOptionsTable::class],
            ['key' => 'customFieldsServices', 'group' => 'custom-fields', 'file' => 'custom-field-services.json',
                'tableClass' => DB\CustomField\CustomFieldsServicesTable::class],
            ['key' => 'customFieldsEvents', 'group' => 'custom-fields', 'file' => 'custom-field-events.json',
                'tableClass' => DB\CustomField\CustomFieldsEventsTable::class],
            ['key' => 'taxes', 'group' => 'finance', 'file' => 'taxes.json',
                'tableClass' => DB\Tax\TaxesTable::class],
            ['key' => 'taxesToEntities', 'group' => 'finance', 'file' => 'taxes-to-entities.json',
                'tableClass' => DB\Tax\TaxesToEntitiesTable::class],
        ];
    }

    private function getDatasetRows(array $dataset): array
    {
        if (($dataset['type'] ?? null) === 'users') {
            $userType = $dataset['userType'];
            $table = UsersTable::getTableName();
            $statement = $this->connection->query(
                "SELECT * FROM `{$table}` WHERE `type` = '" . esc_sql($userType) . "'"
            );

            return array_map(function ($row) {
                return $this->sanitizeDatasetRow($row);
            }, $statement->fetchAll());
        }

        $table = $this->getTableNameFromDataset($dataset);
        $statement = $this->connection->query("SELECT * FROM `{$table}`");

        return array_map(function ($row) {
            return $this->sanitizeDatasetRow($row);
        }, $statement->fetchAll());
    }

    private function sanitizeDatasetRow(array $row): array
    {
        $sanitized = $row;

        foreach ($sanitized as $column => $value) {
            if ($this->isImageColumn($column)) {
                $sanitized[$column] = '';
            }
        }

        return $sanitized;
    }

    private function getSanitizedSettings(): array
    {
        $settings = $this->settingsService->getAllSettingsCategorized();

        unset($settings['activation'], $settings['wordpress']);

        return $this->sanitizeSettingsArray($settings);
    }

    private function sanitizeSettingsArray(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $settings[$key] = $this->sanitizeSettingsArray($value);
                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            if ($this->isImageColumn($key)) {
                $settings[$key] = '';
                continue;
            }

            if (preg_match('/(url|uri)$/i', $key) || preg_match('/(url|uri)/i', $key)) {
                $settings[$key] = '';
            }
        }

        return $settings;
    }

    private function extractAndValidateArchive(string $archivePath, string $extractDirectory): array
    {
        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('The Amelia archive could not be opened.');
        }

        if (!$zip->extractTo($extractDirectory)) {
            $zip->close();
            throw new RuntimeException('The Amelia archive could not be extracted.');
        }

        $zip->close();

        $metadataPath = $extractDirectory . '/metadata.json';

        if (!file_exists($metadataPath)) {
            throw new RuntimeException('The Amelia archive is missing its metadata.json file.');
        }

        $metadata = $this->readJsonFile($metadataPath);

        if (($metadata['formatVersion'] ?? null) !== self::FORMAT_VERSION) {
            throw new RuntimeException('The Amelia archive format is not supported by this version of the plugin.');
        }

        $this->assertMatchingPluginVersion($metadata);
        $this->assertExpectedDatasetsPresent($metadata);

        foreach (($metadata['datasets'] ?? []) as $dataset) {
            if (empty($dataset['path'])) {
                throw new RuntimeException('The Amelia archive metadata is incomplete.');
            }

            $filePath = $extractDirectory . '/' . ltrim($dataset['path'], '/');

            if (!file_exists($filePath)) {
                throw new RuntimeException('The Amelia archive is missing one or more JSON datasets.');
            }

            $this->readJsonFile($filePath);
        }

        return $metadata;
    }

    private function buildImportSteps(array $metadata, string $extractDirectory): array
    {
        $metadataIndex = [];

        foreach (($metadata['datasets'] ?? []) as $dataset) {
            $metadataIndex[$dataset['key']] = $dataset;
        }

        $steps = [];

        foreach ($this->getDatasetDefinitions() as $datasetDefinition) {
            $datasetMetadata = $metadataIndex[$datasetDefinition['key']];
            $datasetPath = $datasetMetadata['path'];
            $datasetData = $this->readJsonData($extractDirectory . '/' . ltrim($datasetPath, '/'));

            $steps[] = array_merge($datasetDefinition, [
                'path' => $datasetPath,
                // Recalculate records from payload to avoid stale/incorrect metadata counts.
                'records' => count($datasetData),
                'processed' => 0,
            ]);
        }

        $steps[] = [
            'key' => 'settings',
            'group' => 'settings',
            'file' => 'settings.json',
            'path' => $metadataIndex['settings']['path'],
            'records' => 1,
            'processed' => 0,
        ];

        if (!$steps) {
            throw new RuntimeException('The Amelia archive does not contain any supported datasets.');
        }

        foreach ($steps as $step) {
            $filePath = $extractDirectory . '/' . $step['path'];

            if (!file_exists($filePath)) {
                throw new RuntimeException('One of the Amelia import datasets is missing.');
            }
        }

        return $steps;
    }

    private function assertMatchingPluginVersion(array $metadata): void
    {
        $archivePluginVersion = (string) ($metadata['pluginVersion'] ?? '');
        $currentPluginVersion = defined('AMELIA_VERSION') ? (string) AMELIA_VERSION : '';

        if ($archivePluginVersion === '' || $currentPluginVersion === '' || $archivePluginVersion !== $currentPluginVersion) {
            throw new RuntimeException('The Amelia archive was created with a different plugin version and cannot be imported.');
        }
    }

    private function assertExpectedDatasetsPresent(array $metadata): void
    {
        $expectedKeys = $this->getExpectedImportDatasetKeys();
        $metadataKeys = array_values(array_unique(array_map(function ($dataset) {
            return (string) ($dataset['key'] ?? '');
        }, $metadata['datasets'] ?? [])));

        $missingKeys = array_values(array_diff($expectedKeys, $metadataKeys));

        if ($missingKeys) {
            throw new RuntimeException(
                'The Amelia archive is incomplete. Missing datasets: ' . implode(', ', $missingKeys)
            );
        }
    }

    private function getExpectedImportDatasetKeys(): array
    {
        $keys = array_map(function ($dataset) {
            return $dataset['key'];
        }, $this->getDatasetDefinitions());

        $keys[] = 'settings';

        return array_values(array_unique($keys));
    }

    private function importSettings(array $settings): void
    {
        $currentSettings = $this->settingsService->getAllSettingsCategorized();
        $mergedSettings = array_replace($currentSettings ?: [], $settings);

        if ($currentSettings) {
            $mergedSettings['activation'] = $currentSettings['activation'] ?? [];
            $mergedSettings['wordpress'] = $currentSettings['wordpress'] ?? [];
        }

        $this->settingsService->setAllSettings($this->sanitizeSettingsArray($mergedSettings));
    }

    private function importDatasetRows(array $dataset, array $rows, array &$job): void
    {
        if (!$rows) {
            return;
        }

        $table = $this->getTableNameFromDataset($dataset);
        $columns = $this->getTableColumns($table);
        $datasetKey = (string) ($dataset['key'] ?? 'unknown');

        $filteredRows = [];
        $sanitizedRows = [];

        foreach ($rows as $row) {
            $sanitizedRow = $this->sanitizeDatasetRow($row);
            $filteredRow = $this->filterRowForTable($sanitizedRow, $columns);

            if (!$filteredRow) {
                $msg = 'Unable to insert an empty row during Amelia import.';
                $job['summary']['skipped']++;
                $this->appendImportError($job, sprintf('Dataset %s: %s', $datasetKey, $msg));

                continue;
            }

            if (($dataset['type'] ?? null) === 'users' && !empty($filteredRow['externalId'])) {
                $this->createWordPressUser($dataset, $filteredRow, $sanitizedRow, $datasetKey, $job);
            }

            $filteredRows[] = $filteredRow;
            $sanitizedRows[] = $sanitizedRow;
        }

        if (!$filteredRows) {
            return;
        }

        $rowCount = count($filteredRows);

        for ($i = 0; $i < $rowCount; $i += self::IMPORT_INSERT_ROWS) {
            $filteredSlice = array_slice($filteredRows, $i, self::IMPORT_INSERT_ROWS);
            $sanitizedSlice = array_slice($sanitizedRows, $i, self::IMPORT_INSERT_ROWS);

            try {
                $this->insertRows($table, $filteredSlice);
                $job['summary']['inserted'] += count($filteredSlice);
            } catch (\Throwable $e) {
                $this->appendImportError(
                    $job,
                    sprintf('Dataset %s: Bulk insert failed (%s), retrying individually.', $datasetKey, $e->getMessage())
                );
                foreach ($sanitizedSlice as $sanitizedRow) {
                    try {
                        $this->insertRow($table, $sanitizedRow);
                        $job['summary']['inserted']++;
                    } catch (\Throwable $rowException) {
                        $msg = $rowException->getMessage();
                        $job['summary']['skipped']++;
                        $this->appendImportError($job, sprintf('Dataset %s: %s', $datasetKey, $msg));
                    }
                }
            }
        }
    }

    /**
     * User rows exported from another site carry a WordPress user id in {@see UsersTable::externalId}
     * that does not exist here. When that column is present and has a value, create (or reuse) a WP
     * account for this site and store its id in the row so the INSERT already links Amelia ↔ WP.
     */
    private function createWordPressUser(
        array $dataset,
        array &$filteredRow,
        array &$sanitizedRow,
        string $datasetKey,
        array &$job
    ): void {
        $email = $filteredRow['email'] ?? '';
        if ($email === '') {
            $filteredRow['externalId'] = null;
            $sanitizedRow['externalId'] = null;
            $this->appendImportError(
                $job,
                sprintf('Dataset %s: Skipped WordPress link (no email) for Amelia user id %s.', $datasetKey, $filteredRow['id'] ?? '?')
            );

            return;
        }

        $userType = $dataset['userType'] ?? '';
        $role = $userType !== '' ? 'wpamelia-' . $userType : null;

        /** @var CreateWPUser $createWpUser */
        $createWpUser = $this->container->get('user.create.wp.user');
        $wpUserId = $createWpUser->create(
            $email,
            (string) ($filteredRow['firstName'] ?? ''),
            (string) ($filteredRow['lastName'] ?? ''),
            $role
        );

        if (!$wpUserId) {
            $filteredRow['externalId'] = null;
            $sanitizedRow['externalId'] = null;
            $this->appendImportError(
                $job,
                sprintf('Dataset %s: WordPress user could not be created for %s; Amelia user imported without link.', $datasetKey, $email)
            );

            return;
        }

        $filteredRow['externalId'] = $wpUserId;
        $sanitizedRow['externalId'] = $wpUserId;
    }

    private function insertRow(string $table, array $row): int
    {
        $columns = $this->getTableColumns($table);
        $filteredRow = $this->filterRowForTable($row, $columns);

        if (!$filteredRow) {
            throw new RuntimeException('Unable to insert an empty row during Amelia import.');
        }

        $this->insertRows($table, [$filteredRow]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param array<int, array<string, mixed>> $filteredRows
     */
    private function insertRows(string $table, array $filteredRows): void
    {
        if (!$filteredRows) {
            return;
        }

        $insertColumns = $this->getTableColumns($table);

        if (!$insertColumns) {
            throw new RuntimeException('Unable to insert an empty row during Amelia import.');
        }

        $quotedColumns = array_map(function ($column) {
            return "`{$column}`";
        }, $insertColumns);

        $valueGroups = [];
        $parameters = [];
        $placeholderIndex = 0;

        foreach ($filteredRows as $filteredRow) {
            $placeholders = [];

            foreach ($insertColumns as $column) {
                $placeholder = ':p' . $placeholderIndex++;
                $placeholders[] = $placeholder;
                $parameters[$placeholder] = array_key_exists($column, $filteredRow)
                    ? $filteredRow[$column]
                    : null;
            }

            $valueGroups[] = '(' . implode(', ', $placeholders) . ')';
        }

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES %s',
            $table,
            implode(', ', $quotedColumns),
            implode(', ', $valueGroups)
        );

        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);
    }

    private function filterRowForTable(array $row, array $columns): array
    {
        $filteredRow = [];

        foreach ($row as $column => $value) {
            if (!in_array($column, $columns, true)) {
                continue;
            }

            $filteredRow[$column] = $value;
        }

        return $filteredRow;
    }

    private function getTableColumns(string $table): array
    {
        if (isset($this->columnCache[$table])) {
            return $this->columnCache[$table];
        }

        $statement = $this->connection->query("SHOW COLUMNS FROM `{$table}`");
        $columns = [];

        foreach ($statement->fetchAll() as $column) {
            $columns[] = $column['Field'];
        }

        $this->columnCache[$table] = $columns;

        return $columns;
    }

    private function getTableNameFromDataset(array $dataset): string
    {
        $tableClass = $dataset['tableClass'] ?? null;

        if (!$tableClass || !method_exists($tableClass, 'getTableName')) {
            throw new RuntimeException('The Amelia import/export dataset configuration is invalid.');
        }

        return $tableClass::getTableName();
    }

    private function readJsonData(string $path): array
    {
        $data = $this->readJsonFile($path);

        return $data['data'] ?? [];
    }

    private function readJsonFile(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('One of the Amelia import files could not be read.');
        }

        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('One of the Amelia import files contains invalid JSON.');
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function formatJobStatus(array $job): array
    {
        $currentStep = $job['steps'][$job['currentStep']] ?? null;
        $percentage = (int) min(100, round(($job['processedRows'] / max(1, $job['totalRows'])) * 100));
        $steps = array_map(function ($step) {
            return [
                'key' => $step['key'],
                'processed' => (int) ($step['processed'] ?? 0),
                'records' => (int) ($step['records'] ?? 0),
            ];
        }, $job['steps'] ?? []);

        return [
            'jobId' => $job['id'],
            'status' => $job['status'],
            'percentage' => $percentage,
            'processedRows' => $job['processedRows'],
            'totalRows' => $job['totalRows'],
            'currentDataset' => $currentStep['key'] ?? null,
            'currentDatasetProcessed' => (int) ($currentStep['processed'] ?? 0),
            'currentDatasetTotal' => (int) ($currentStep['records'] ?? 0),
            'steps' => $steps,
            'summary' => $job['summary'],
            'errors' => $job['errors'] ?? [],
            'updatedAt' => $job['updatedAt'],
        ];
    }

    private function appendImportError(array &$job, string $message): void
    {
        if (!isset($job['errors']) || !is_array($job['errors'])) {
            $job['errors'] = [];
        }

        $job['errors'][] = $message;
    }

    private function getJob(string $jobId): ?array
    {
        $job = get_option($this->getJobOptionName($jobId));

        return is_array($job) ? $job : null;
    }

    private function saveJob(array $job): void
    {
        update_option($this->getJobOptionName($job['id']), $job, false);
    }

    private function getJobOptionName(string $jobId): string
    {
        return self::JOB_OPTION_PREFIX . $jobId;
    }

    private function createWorkingDirectory(string $jobId): string
    {
        $uploads = wp_upload_dir();
        $baseDirectory = trailingslashit($uploads['basedir']) . 'amelia/data-transfer';
        $workingDirectory = $baseDirectory . '/' . sanitize_file_name($jobId);

        wp_mkdir_p($workingDirectory);

        return $workingDirectory;
    }

    private function truncateAllTables(): void
    {
        $datasets = $this->getDatasetDefinitions();
        $tablesToTruncate = [];

        foreach ($datasets as $dataset) {
            if (isset($dataset['tableClass'])) {
                $tableName = call_user_func([$dataset['tableClass'], 'getTableName']);
                $tablesToTruncate[] = $tableName;
            } elseif (($dataset['type'] ?? null) === 'users') {
                $tablesToTruncate[] = UsersTable::getTableName();
            }
        }

        $tablesToTruncate[] = GalleriesTable::getTableName();

        $tablesToTruncate = array_values(array_unique($tablesToTruncate));

        try {
            foreach (array_reverse($tablesToTruncate) as $table) {
                $this->connection->query("TRUNCATE TABLE `{$table}`");
            }
        } catch (\Exception $e) {
            throw new RuntimeException('Unable to truncate Amelia tables before import: ' . $e->getMessage());
        }
    }

    private function cleanupJobFiles(array $job, bool $deleteJobOption = true): void
    {
        if (!empty($job['archivePath']) && file_exists($job['archivePath'])) {
            @unlink($job['archivePath']);
        }

        if (!empty($job['extractDirectory']) && is_dir($job['extractDirectory'])) {
            $this->deleteDirectory(dirname($job['extractDirectory']));
        }

        if ($deleteJobOption) {
            delete_option($this->getJobOptionName($job['id']));
        }
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if (in_array($item, ['.', '..'], true)) {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    private function assertZipArchiveAvailable(): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive is not available on this server.');
        }
    }

    private function hasAmeliaFileExtension(string $fileName): bool
    {
        return substr(strtolower($fileName), -11) === '.ameliafile';
    }

    private function isImageColumn(string $column): bool
    {
        return (bool) preg_match('/(image|gallery|picture|thumb|fullPath)/i', $column);
    }
}
