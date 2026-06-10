<?php

declare(strict_types=1);

namespace Melograno\UsageTracker\Collectors\Plugin;

use Melograno\UsageTracker\Collectors\BaseCollector;

class AmeliaCollector extends BaseCollector
{
    /** @var array<string, string> */
    private const LICENSE_TIER_MAP = [
        'lite' => 'lite',
        'starter' => 'starter',
        'basic' => 'standard',
        'pro' => 'pro',
        'developer' => 'elite',
    ];

    public function getPluginSlug(): string
    {
        return 'ameliabooking';
    }

    public function getConsentOptionName(): string
    {
        return 'amelia_usage_tracking_consent';
    }

    /**
     * Maps Amelia activation.licence values to telemetry license slugs.
     */
    public static function normalizeLicenseTier(?string $ameliaLicence): ?string
    {
        if ($ameliaLicence === null) {
            return null;
        }

        $trimmed = trim($ameliaLicence);
        if ($trimmed === '') {
            return 'elite';
        }

        $key = strtolower($trimmed);

        return self::LICENSE_TIER_MAP[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function pluginPayload(): array
    {
        $container = require AMELIA_PATH . '/src/Infrastructure/ContainerConfig/container.php';
        $customerBookingRepository = $container->get('domain.booking.customerBooking.repository');
        $appointmentRepository = $container->get('domain.booking.appointment.repository');

        $data = [
            'plugin_version' => AMELIA_VERSION,
        ];

        $rawLicence = $this->resolveAmeliaLicence();
        $licenseTier = self::normalizeLicenseTier($rawLicence);
        if ($licenseTier !== null) {
            $data['license'] = $licenseTier;
        }

        $minCreated = $customerBookingRepository->getEarliestCreatedAt();
        if ($minCreated !== null && $minCreated !== '') {
            $timestamp = strtotime($minCreated);
            if ($timestamp !== false) {
                $data['first_booking_created_at'] = gmdate('c', $timestamp);
            }
        }

        $data['appointment_bookings_count'] = $customerBookingRepository->getAppointmentBookingsCount();
        $data['appointments_count'] = $appointmentRepository->getAppointmentsCount();
        $data['event_bookings_count'] = $customerBookingRepository->getEventBookingsCount();
        $data['approved_appointment_bookings_count'] = $customerBookingRepository->getApprovedAppointmentBookingsCount();
        $data['approved_appointments_count'] = $appointmentRepository->getApprovedAppointmentsCount();

        return array_filter($data, static function ($value) {
            return $value !== null;
        });
    }

    protected function resolveAmeliaLicence(): ?string
    {
        $licence = \AmeliaBooking\Infrastructure\Licence\Licence::getLicence();

        return is_string($licence) ? $licence : null;
    }
}
