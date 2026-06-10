<?php

declare(strict_types=1);

namespace Melograno\UsageTracker\Tests;

use Melograno\UsageTracker\Collectors\BaseCollector;
use Melograno\UsageTracker\Core\UsageTracker;
use PHPUnit\Framework\TestCase;

class UsageTrackerSettingsTest extends TestCase
{
    public const OPTION = 'test_plugin_usage_tracking_consent';

    protected function tearDown(): void
    {
        delete_option(self::OPTION);
        parent::tearDown();
    }

    private function collector(): BaseCollector
    {
        return new class () extends BaseCollector {
            public function getPluginSlug(): string
            {
                return 'test-plugin';
            }

            public function getConsentOptionName(): string
            {
                return UsageTrackerSettingsTest::OPTION;
            }

            protected function pluginPayload(): array
            {
                return [];
            }
        };
    }

    public function testGetSettingsReturnsConsentState(): void
    {
        $collector = $this->collector();

        UsageTracker::setConsent($collector, true);
        $settings = UsageTracker::getSettings($collector);

        $this->assertArrayHasKey('usageTrackingEnabled', $settings);
        $this->assertTrue($settings['usageTrackingEnabled']);
    }

    public function testGetSettingsReturnsDisabledWhenConsentOff(): void
    {
        $collector = $this->collector();

        UsageTracker::setConsent($collector, false);
        $settings = UsageTracker::getSettings($collector);

        $this->assertFalse($settings['usageTrackingEnabled']);
    }

    public function testUpdateSettingsAppliesConsent(): void
    {
        $collector = $this->collector();

        UsageTracker::setConsent($collector, true);

        $settings = ['usageTrackingEnabled' => false, 'someOtherKey' => 'value'];
        UsageTracker::updateSettings($collector, $settings);

        $this->assertFalse(UsageTracker::isConsentEnabled($collector));
    }

    public function testUpdateSettingsUnsetsHandledKeys(): void
    {
        $collector = $this->collector();

        $settings = ['usageTrackingEnabled' => true, 'someOtherKey' => 'value'];
        UsageTracker::updateSettings($collector, $settings);

        $this->assertArrayNotHasKey('usageTrackingEnabled', $settings);
        $this->assertArrayHasKey('someOtherKey', $settings);
        $this->assertSame('value', $settings['someOtherKey']);
    }

    public function testUpdateSettingsIgnoresMissingKeys(): void
    {
        $collector = $this->collector();

        UsageTracker::setConsent($collector, true);

        $settings = ['unrelatedKey' => 42];
        UsageTracker::updateSettings($collector, $settings);

        $this->assertTrue(UsageTracker::isConsentEnabled($collector));
        $this->assertSame(['unrelatedKey' => 42], $settings);
    }
}
