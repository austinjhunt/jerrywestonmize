<?php

declare(strict_types=1);

namespace Melograno\UsageTracker\Tests;

use Brain\Monkey;
use Melograno\UsageTracker\Collectors\BaseCollector;
use Melograno\UsageTracker\Collectors\Plugin\AmeliaCollector;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AmeliaCollectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        if (!defined('AMELIA_VERSION')) {
            define('AMELIA_VERSION', '1.0.0-test');
        }
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testCollectorIdentity(): void
    {
        $collector = new AmeliaCollector();

        $this->assertSame('ameliabooking', $collector->getPluginSlug());
        $this->assertSame('amelia_usage_tracking_consent', $collector->getConsentOptionName());
    }

    public function testCronHookName(): void
    {
        $collector = new AmeliaCollector();

        $this->assertSame('melograno_usage_tracker_ameliabooking_send', $collector->getCronHookName());
        $this->assertSame(
            $collector->getCronHookName(),
            BaseCollector::cronHookNameForSlug($collector->getPluginSlug())
        );
    }

    public function testCronSchedule(): void
    {
        $this->assertSame('weekly', (new AmeliaCollector())->getCronSchedule());
    }

    /**
     * @dataProvider licenseTierProvider
     */
    public function testNormalizeLicenseTier(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, AmeliaCollector::normalizeLicenseTier($input));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public function licenseTierProvider(): array
    {
        return [
            'lite' => ['Lite', 'lite'],
            'starter' => ['Starter', 'starter'],
            'basic' => ['Basic', 'standard'],
            'pro' => ['Pro', 'pro'],
            'developer' => ['Developer', 'elite'],
            'empty string' => ['', 'elite'],
            'whitespace' => ['  ', 'elite'],
            'null' => [null, null],
            'unknown' => ['Enterprise', null],
        ];
    }

    public function testPluginPayloadIncludesLicenseFromGetLicence(): void
    {
        $collector = new class () extends AmeliaCollector {
            protected function resolveAmeliaLicence(): ?string
            {
                return 'Pro';
            }

            protected function pluginPayload(): array
            {
                $data = [
                    'plugin_version' => AMELIA_VERSION,
                    'appointment_bookings_count' => 0,
                    'appointments_count' => 0,
                    'event_bookings_count' => 0,
                    'approved_appointment_bookings_count' => 0,
                    'approved_appointments_count' => 0,
                ];

                $licenseTier = self::normalizeLicenseTier($this->resolveAmeliaLicence());
                if ($licenseTier !== null) {
                    $data['license'] = $licenseTier;
                }

                return array_filter($data, static function ($value) {
                    return $value !== null;
                });
            }
        };

        $payload = $this->invokePluginPayload($collector);

        $this->assertSame('pro', $payload['license'] ?? null);
        $this->assertArrayNotHasKey('first_booking_created_at', $payload);
    }

    public function testPluginPayloadOmitsLicenseWhenLicenceUnavailable(): void
    {
        $collector = new class () extends AmeliaCollector {
            protected function resolveAmeliaLicence(): ?string
            {
                return null;
            }

            protected function pluginPayload(): array
            {
                $data = [
                    'plugin_version' => AMELIA_VERSION,
                    'appointment_bookings_count' => 0,
                    'appointments_count' => 0,
                    'event_bookings_count' => 0,
                    'approved_appointment_bookings_count' => 0,
                    'approved_appointments_count' => 0,
                ];

                $licenseTier = self::normalizeLicenseTier($this->resolveAmeliaLicence());
                if ($licenseTier !== null) {
                    $data['license'] = $licenseTier;
                }

                return array_filter($data, static function ($value) {
                    return $value !== null;
                });
            }
        };

        $payload = $this->invokePluginPayload($collector);

        $this->assertArrayNotHasKey('license', $payload);
    }

    public function testPluginPayloadIncludesFirstBookingCreatedAt(): void
    {
        $collector = new class () extends AmeliaCollector {
            protected function resolveAmeliaLicence(): ?string
            {
                return null;
            }

            protected function pluginPayload(): array
            {
                $data = [
                    'plugin_version' => AMELIA_VERSION,
                    'appointment_bookings_count' => 0,
                    'appointments_count' => 0,
                    'event_bookings_count' => 0,
                    'approved_appointment_bookings_count' => 0,
                    'approved_appointments_count' => 0,
                ];

                $minCreated = '2024-03-15 10:00:00';
                $timestamp = strtotime($minCreated);
                if ($timestamp !== false) {
                    $data['first_booking_created_at'] = gmdate('c', $timestamp);
                }

                return array_filter($data, static function ($value) {
                    return $value !== null;
                });
            }
        };

        $payload = $this->invokePluginPayload($collector);

        $this->assertSame('2024-03-15T10:00:00+00:00', $payload['first_booking_created_at'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function invokePluginPayload(AmeliaCollector $collector): array
    {
        $reflection = new ReflectionClass($collector);
        $method = $reflection->getMethod('pluginPayload');
        $method->setAccessible(true);

        return $method->invoke($collector);
    }
}
