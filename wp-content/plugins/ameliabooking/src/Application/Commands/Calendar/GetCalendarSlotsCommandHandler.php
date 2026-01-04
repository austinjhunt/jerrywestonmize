<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Commands\Calendar;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Entity\Schedule\DayOff;
use AmeliaBooking\Domain\Entity\Schedule\SpecialDay;
use AmeliaBooking\Domain\Entity\Schedule\WeekDay;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\ValueObjects\String\BookingStatus;
use AmeliaBooking\Domain\ValueObjects\String\Status;
use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeZone;

class GetCalendarSlotsCommandHandler extends CommandHandler
{
    private $timeLimits = ['slotMinTime' => '24:00:00', 'slotMaxTime' => '00:00:00'];

    public function handle(GetCalendarSlotsCommand $command): CommandResult
    {
        $result = new CommandResult();

        $providerRepository = $this->container->get('domain.users.providers.repository');
        $locationRepository = $this->container->get('domain.locations.repository');

        $queryParams = $command->getField('queryParams');
        $allWorkDays = [];
        $selectedService = $queryParams['service'] ?? null;

        $queryParams['locations'] = array_map(
            fn($location) => $location['id'],
            $locationRepository->getFiltered(
                ['status' => !empty($queryParams['providers']) ? null : Status::VISIBLE],
                0
            )->toArray()
        );

        $criteria = ['providerStatus' => !empty($queryParams['providers']) ? null : Status::VISIBLE];
        foreach ($queryParams as $key => $value) {
            if ($key !== 'providerStatus') {
                $criteria[$key] = $value;
            }
        }

        $providers = $providerRepository->getWithSchedule($criteria)->getItems();

        foreach ($providers as $provider) {
            if (!$selectedService) {
                $providerWorkDays = $this->getProviderWorkDays($provider, $queryParams);
                $this->getTimeLimitsByProvider($queryParams, $providerWorkDays, $provider);
                $this->mergeProviderWorkDays($allWorkDays, $providerWorkDays);
            }
        }

        if (empty($allWorkDays)) {
            $this->fillEmptyWorkDays($allWorkDays, $queryParams);
        }

        $this->processCompanyDaysOff($allWorkDays, $queryParams);
        $formattedWorkPeriods = $this->formatWorkDays($allWorkDays);

        $this->getTimeLimitsFromAppointmentsAndEvents($queryParams);

        $result->setData([
            'workPeriods' => $formattedWorkPeriods,
            'slotMinTime' => $this->timeLimits['slotMinTime'],
            'slotMaxTime' => $this->timeLimits['slotMaxTime'],
            'now' => DateTimeService::getNowDateTime()
        ]);

        return $result;
    }

    private function fillEmptyWorkDays(array &$allWorkDays, array $queryParams): void
    {
        [$this->timeLimits['slotMinTime'], $this->timeLimits['slotMaxTime']] = $this->getLimitsFromCompanyWorkHours();

        if ($this->timeLimits['slotMinTime'] === '24:00:00' && $this->timeLimits['slotMaxTime'] === '00:00:00') {
            $this->timeLimits['slotMinTime'] = '09:00:00';
            $this->timeLimits['slotMaxTime'] = '17:00:00';
        }

        $calendarStartDate = DateTime::createFromFormat('Y-m-d', $queryParams['calendarStartDate']);
        $calendarEndDate = DateTime::createFromFormat('Y-m-d', $queryParams['calendarEndDate']);

        $datePeriod = new DatePeriod($calendarStartDate, new DateInterval('P1D'), $calendarEndDate);

        foreach ($datePeriod as $date) {
            $dateString = $date->format('Y-m-d');
            $allWorkDays[$dateString] = ['groupId' => 'notWorkHours', 'periods' => []];
        }
    }

    private function getProviderWorkDays(Provider $provider, array $queryParams): array
    {
        $providerTimeZone = $provider->getTimezone() ? $provider->getTimezone()->getValue() : DateTimeService::getTimeZone()->getName();
        $currentUserTimeZone = DateTimeService::getTimeZone();

        $employeeDays = [];
        $startDate = new DateTime($queryParams['calendarStartDate'], $currentUserTimeZone);
        $endDate = new DateTime($queryParams['calendarEndDate'], $currentUserTimeZone);

        $datePeriod = new DatePeriod($startDate, new DateInterval('P1D'), $endDate);

        $weekDays = $provider->getWeekDayList()->getItems();
        $specialDays = $provider->getSpecialDayList()->getItems();
        $daysOff = $provider->getDayOffList()->getItems();

        foreach ($datePeriod as $date) {
            $dateString = $date->format('Y-m-d');
            $weekDay = $this->findMatchingDay($weekDays, $date->format('N'));

            $this->mapPeriods(
                $employeeDays,
                $weekDay ? $weekDay->getPeriodList()->getItems() : [],
                $dateString,
                $providerTimeZone,
                $currentUserTimeZone,
                'workHours'
            );

            $specialDay = $this->findMatchingSpecialDay($specialDays, $date);

            if ($specialDay) {
                $this->mapPeriods(
                    $employeeDays,
                    $specialDay->getPeriodList()->getItems(),
                    $dateString,
                    $providerTimeZone,
                    $currentUserTimeZone,
                    'workHours specialDay'
                );
            }

            $dayOff = $this->findMatchingDayOff($daysOff, $date);
            if ($dayOff) {
                $employeeDays[$dateString] = [
                    'groupId' => 'dayOff',
                    'periods' => []
                ];
            }
        }

        return $employeeDays;
    }

    private function findMatchingDay(array $weekDays, int $dateDayIndex): ?WeekDay
    {
        foreach ($weekDays as $weekDay) {
            if ($weekDay->getDayIndex()->getValue() === $dateDayIndex) {
                return $weekDay;
            }
        }
        return null;
    }

    private function findMatchingSpecialDay(array $specialDays, DateTime $date): ?SpecialDay
    {
        foreach ($specialDays as $specialDay) {
            if ($date >= $specialDay->getStartDate()->getValue() && $date <= $specialDay->getEndDate()->getValue()) {
                return $specialDay;
            }
        }
        return null;
    }

    private function findMatchingDayOff(array $daysOff, DateTime $date): ?DayOff
    {
        foreach ($daysOff as $dayOff) {
            if ($date >= $dayOff->getStartDate()->getValue() && $date <= $dayOff->getEndDate()->getValue()) {
                return $dayOff;
            }
        }
        return null;
    }

    private function mapPeriods(
        array &$employeeDays,
        array $periods,
        string $dateString,
        string $providerTimeZone,
        DateTimeZone $currentUserTimeZone,
        string $groupId = 'workHours'
    ): void {
        if (!isset($employeeDays[$dateString])) {
            $employeeDays[$dateString] = [
                'groupId' => 'workHours',
                'periods' => []
            ];
        }

        foreach ($periods as $period) {
            $startDateTime = $this->convertWorkPeriods(
                new DateTime($dateString . $period->getStartTime()->getValue()->format('H:i:s')),
                $providerTimeZone,
                $currentUserTimeZone
            );

            $endDateTime = $this->convertWorkPeriods(
                new DateTime($dateString . $period->getEndTime()->getValue()->format('H:i:s')),
                $providerTimeZone,
                $currentUserTimeZone
            );

            $startDate = $startDateTime->format('Y-m-d');
            $startTime = $startDateTime->format('H:i:s');
            $endDate = $endDateTime->format('Y-m-d');
            $endTime = $endDateTime->format('H:i:s');

            if ($startDate !== $dateString || $endDate !== $dateString) {
                if (!isset($employeeDays[$startDate])) {
                    $employeeDays[$startDate] = [
                        'groupId' => 'workHours',
                        'periods' => []
                    ];
                }

                if (!isset($employeeDays[$endDate])) {
                    $employeeDays[$endDate] = [
                        'groupId' => 'workHours',
                        'periods' => []
                    ];
                }

                $employeeDays[$startDate]['periods'][] = ['groupId' => $groupId, 'start' => $startTime, 'end' => '24:00:00'];
                $employeeDays[$endDate]['periods'][] = ['groupId' => $groupId, 'start' => '00:00:00', 'end' => $endTime];

                continue;
            }

            $employeeDays[$dateString]['periods'][] = [
                'groupId' => $groupId,
                'start' => $startTime,
                'end' => $endTime === '00:00:00' ? '24:00:00' : $endTime
            ];
        }
    }

    private function mergeProviderWorkDays(array &$allWorkDays, array $providerWorkDays): void
    {
        foreach ($providerWorkDays as $date => $info) {
            if (!isset($allWorkDays[$date])) {
                $allWorkDays[$date] = $info;

                continue;
            }

            if ($allWorkDays[$date]['groupId'] === 'workHours' && $info['groupId'] === 'dayOff') {
                continue;
            }

            foreach ($info['periods'] as $period) {
                $merged = false;
                foreach ($allWorkDays[$date]['periods'] as &$existingPeriod) {
                    if (
                        $period['groupId'] === $existingPeriod['groupId'] &&
                        $period['start'] <= $existingPeriod['end'] &&
                        $period['end'] >= $existingPeriod['start']
                    ) {
                        $existingPeriod['start'] = min($existingPeriod['start'], $period['start']);
                        $existingPeriod['end'] = max($existingPeriod['end'], $period['end']);
                        $merged = true;
                        break;
                    }
                }

                if (!$merged) {
                    $allWorkDays[$date]['periods'][] = $period;
                }
            }
        }
    }

    private function formatWorkDays(array $allWorkDays): array
    {
        $formattedPeriods = [];

        foreach ($allWorkDays as $date => $info) {
            if ($info['groupId'] === 'dayOff') {
                $formattedPeriods[] = $this->createPeriod($date, $date, 'dayOff', 'day-off');
                continue;
            }

            $periods = $info['periods'];
            if (empty($periods)) {
                $formattedPeriods[] = $this->createPeriod($date, $date, 'notWorkHours', 'not-work-hours');
                continue;
            }

            usort($periods, fn($a, $b) => $a['start'] <=> $b['start']);

            foreach ($periods as $i => $period) {
                $start = "{$date}T{$period['start']}";
                $end = "{$date}T{$period['end']}";

                if ($i === 0 && $period['start'] !== '00:00:00') {
                    $formattedPeriods[] = $this->createPeriod("{$date}T00:00:00", $start, 'notWorkHours', 'not-work-hours');
                }

                $formattedPeriods[] = $this->createPeriod($start, $end, 'workHours', 'work-hours');

                if (isset($periods[$i + 1]) && $period['end'] !== $periods[$i + 1]['start']) {
                    $formattedPeriods[] = $this->createPeriod($end, "{$date}T{$periods[$i + 1]['start']}", 'notWorkHours', 'not-work-hours');
                }

                if ($i === count($periods) - 1 && $period['end'] !== '24:00:00') {
                    $formattedPeriods[] = $this->createPeriod($end, "{$date}T24:00:00", 'notWorkHours', 'not-work-hours');
                }
            }
        }

        return $formattedPeriods;
    }

    private function createPeriod(string $start, string $end, string $groupId, string $className): array
    {
        return [
            'groupId' => $groupId,
            'start' => $start,
            'end' => $end,
            'display' => 'background',
            'className' => $className
        ];
    }

    private function getTimeLimitsByProvider(array $queryParams, array $periods, Provider $provider): void
    {
        [$this->timeLimits['slotMinTime'], $this->timeLimits['slotMaxTime']] = $this->getTimeLimitsFromPeriods(
            $periods,
            $this->timeLimits['slotMinTime'],
            $this->timeLimits['slotMaxTime']
        );

        if ($this->timeLimits['slotMinTime'] === '24:00:00' && $this->timeLimits['slotMaxTime'] === '00:00:00') {
            [$this->timeLimits['slotMinTime'], $this->timeLimits['slotMaxTime']] = $this->getLimitsFromCompanyWorkHours();
        }

        if ($this->timeLimits['slotMinTime'] === '24:00:00' && $this->timeLimits['slotMaxTime'] === '00:00:00') {
            $this->timeLimits['slotMinTime'] = '09:00:00';
            $this->timeLimits['slotMaxTime'] = '17:00:00';
        }
    }

    private function getTimeLimitsFromPeriods(array $providerWorkDays, string $slotMinTime, string $slotMaxTime): array
    {
        foreach ($providerWorkDays as $providerWorkDay) {
            foreach ($providerWorkDay['periods'] as $period) {
                $slotMinTime = min($slotMinTime, $period['start']);
                $slotMaxTime = max($slotMaxTime, $period['end']);
            }
        }

        return [$slotMinTime, $slotMaxTime];
    }

    private function getLimitsFromCompanyWorkHours(): array
    {
        $settingsDS = $this->container->get('domain.settings.service');
        $slotMinTime = '24:00:00';
        $slotMaxTime = '00:00:00';
        $companyWorkHours = $settingsDS->getCategorySettings('weekSchedule');

        foreach ($companyWorkHours as $companyWorkHour) {
            if (!is_null($companyWorkHour['time'][0]) && !is_null($companyWorkHour['time'][1])) {
                $slotMinTime = min($slotMinTime, $companyWorkHour['time'][0] . ':00');
                $slotMaxTime = max($slotMaxTime, $companyWorkHour['time'][1] . ':00');
            }
        }

        return [$slotMinTime, $slotMaxTime];
    }

    private function getTimeLimitsFromAppointmentsAndEvents(array $queryParams): void
    {
        if (isset($queryParams['entitiesToShow']) && in_array('appointments', $queryParams['entitiesToShow'])) {
            [$this->timeLimits['slotMinTime'], $this->timeLimits['slotMaxTime']] =
                $this->getLimitsForAppointments($queryParams, $this->timeLimits['slotMinTime'], $this->timeLimits['slotMaxTime']);
        }

        if (isset($queryParams['entitiesToShow']) && in_array('events', $queryParams['entitiesToShow'])) {
            [$this->timeLimits['slotMinTime'], $this->timeLimits['slotMaxTime']] =
                $this->getLimitsForEvents($queryParams, $this->timeLimits['slotMinTime'], $this->timeLimits['slotMaxTime']);
        }
    }

    private function getLimitsForAppointments($queryParams, $slotMinTime, $slotMaxTime): array
    {
        $appointmentRepository = $this->container->get('domain.booking.appointment.repository');

        $queryParams['calendarStartDate'] = DateTimeService::getCustomDateTimeInUtc($queryParams['calendarStartDate'] . ' 00:00:00');
        $queryParams['calendarEndDate'] = DateTimeService::getCustomDateTimeInUtc($queryParams['calendarEndDate'] . ' 23:59:59');

        $statuses = isset($queryParams['statuses']) && in_array('pendingAppointments', $queryParams['statuses'])
            ? [BookingStatus::APPROVED, BookingStatus::PENDING]
            : [BookingStatus::APPROVED];

        $appointments = $appointmentRepository->getFiltered([
            'dates'     => [$queryParams['calendarStartDate'], $queryParams['calendarEndDate']],
            'providers' => !empty($queryParams['providers']) ? $queryParams['providers'] : [],
            'statuses'  => $statuses
        ]);

        foreach ($appointments->getItems() as $appointment) {
            $startDateTime = $appointment->getBookingStart()->getValue()->sub(new DateInterval(
                'PT' . abs($appointment->getService()->getTimeBefore() ? $appointment->getService()->getTimeBefore()->getValue() : 0) . 'S'
            ));
            $endDateTime = $appointment->getBookingEnd()->getValue()->add(new DateInterval(
                'PT' . abs($appointment->getService()->getTimeAfter() ? $appointment->getService()->getTimeAfter()->getValue() : 0) . 'S'
            ));

            $slotMinTime = min($slotMinTime, $startDateTime->format('H:i:s'));
            $slotMaxTime = max($slotMaxTime, $endDateTime->format('H:i:s'));
        }

        return [$slotMinTime, $slotMaxTime];
    }

    private function getLimitsForEvents($queryParams, $slotMinTime, $slotMaxTime): array
    {
        $eventAS = $this->container->get('application.booking.event.service');

        $statuses = isset($queryParams['statuses']) && in_array('pendingAppointments', $queryParams['statuses'])
            ? [BookingStatus::APPROVED, BookingStatus::PENDING]
            : [BookingStatus::APPROVED];

        $events = $eventAS->getEventsByCriteria(
            [
                'dates'     => [$queryParams['calendarStartDate'], $queryParams['calendarEndDate']],
                'providers' => !empty($queryParams['providers']) ? $queryParams['providers'] : null,
                'statuses'  => $statuses
            ],
            ['fetchEventsPeriods' => true],
            -1
        );

        foreach ($events->getItems() as $event) {
            foreach ($event->getPeriods()->getItems() as $period) {
                $startDateTime = $period->getPeriodStart()->getValue()->format('H:i:s');
                $endDateTime   = $period->getPeriodEnd()->getValue()->format('H:i:s');

                $slotMinTime = min($slotMinTime, $startDateTime);
                $slotMaxTime = max($slotMaxTime, $endDateTime);
            }
        }

        return [$slotMinTime, $slotMaxTime];
    }

    private function processCompanyDaysOff(array &$allWorkDays, array $queryParams): void
    {
        $isDateRangeOverlapping = fn(DateTime $start1, DateTime $end1, DateTime $start2, DateTime $end2): bool =>
        $start1 <= $end2 && $end1 >= $start2;

        $settingsDS = $this->container->get('domain.settings.service');
        $calendarStartDate = DateTime::createFromFormat('Y-m-d', $queryParams['calendarStartDate']);
        $calendarEndDate = DateTime::createFromFormat('Y-m-d', $queryParams['calendarEndDate']);

        $companyDaysOff = $settingsDS->getCategorySettings('daysOff');

        foreach ($companyDaysOff as $key => $companyDayOff) {
            $dayOffStartDate = DateTime::createFromFormat('Y-m-d', $companyDayOff['startDate']);
            $dayOffEndDate = DateTime::createFromFormat('Y-m-d', $companyDayOff['endDate']);

            if (!$isDateRangeOverlapping($calendarStartDate, $calendarEndDate, $dayOffStartDate, $dayOffEndDate)) {
                unset($companyDaysOff[$key]);
            }
        }

        foreach ($allWorkDays as $date => $info) {
            $periodDateTime = DateTime::createFromFormat('Y-m-d', $date)->setTime(0, 0);

            foreach ($companyDaysOff as $dayOff) {
                $dayOffStartDate = DateTime::createFromFormat('Y-m-d', $dayOff['startDate'])->setTime(0, 0);
                $dayOffEndDate = DateTime::createFromFormat('Y-m-d', $dayOff['endDate'])->setTime(0, 0);

                if ($dayOffStartDate <= $periodDateTime && $dayOffEndDate >= $periodDateTime) {
                    $allWorkDays[$date] = ['groupId' => 'dayOff', 'periods' => []];
                    break;
                }
            }
        }
    }

    private function convertWorkPeriods($period, string $providerTimezone, DateTimeZone $userTimezone): DateTime
    {
        return (new DateTime($period->format('Y-m-d H:i:s'), new DateTimeZone($providerTimezone)))
            ->setTimezone($userTimezone);
    }

    private function convertTimeLimits(string $slotMinTime, string $slotMaxTime, Provider $provider): array
    {
        $providerTimezone = ($provider->getTimezone() ? $provider->getTimezone()->getValue() : DateTimeService::getTimeZone()->getName());
        $slotMinTime      = $this->applyTimezone($slotMinTime, $providerTimezone);
        $slotMaxTime      = $this->applyTimezone($slotMaxTime, $providerTimezone);

        if ($slotMinTime >= $slotMaxTime) {
            return ['00:00:00', '24:00:00'];
        }

        return [$slotMinTime, $slotMaxTime];
    }

    private function applyTimezone(string $time, string $providerTimezone): string
    {
        return (new DateTime($time, new DateTimeZone($providerTimezone)))
            ->setTimezone(DateTimeService::getTimeZone())
            ->format('H:i:s');
    }
}
