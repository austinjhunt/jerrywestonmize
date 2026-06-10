<?php

declare(strict_types=1);

namespace Melograno\UsageTracker\Core;

use Melograno\UsageTracker\Collectors\PluginCollectorInterface;

class UsageTracker
{
    /** @var array<string, bool> */
    private static $bootstrapped = [];

    /** @var array<string, PluginCollectorInterface> keyed by plugin file basename */
    private static $collectors = [];

    /** @var PluginCollectorInterface */
    private $collector;

    /** @var ConsentManager */
    private $consentManager;

    /** @var Anonymizer */
    private $anonymizer;

    /** @var HttpClient */
    private $httpClient;

    public function __construct(
        PluginCollectorInterface $collector,
        ?ConsentManager $consentManager = null,
        ?Anonymizer $anonymizer = null,
        ?HttpClient $httpClient = null
    ) {
        $this->collector = $collector;
        $this->consentManager = $consentManager ?? new ConsentManager($collector->getConsentOptionName());
        $this->anonymizer = $anonymizer ?? new Anonymizer();
        $this->httpClient = $httpClient ?? new HttpClient();
    }

    /**
     * @param string|null $pluginFile Host plugin main file path; registers activation/deactivation hooks when set.
     */
    public static function init(PluginCollectorInterface $collector, ?string $pluginFile = null): void
    {
        $slug = $collector->getPluginSlug();

        if ($pluginFile !== null) {
            self::$collectors[plugin_basename($pluginFile)] = $collector;

            register_activation_hook(
                $pluginFile,
                static function () use ($collector): void {
                    self::activateDefaultConsent($collector);
                }
            );
            register_deactivation_hook(
                $pluginFile,
                static function () use ($collector): void {
                    self::unschedule($collector);
                }
            );
            register_uninstall_hook($pluginFile, [self::class, 'handleUninstall']);
        }

        if (isset(self::$bootstrapped[$slug])) {
            return;
        }

        self::$bootstrapped[$slug] = true;

        $tracker = new self($collector);

        $tracker->boot();
    }

    /**
     * Serializable uninstall callback invoked by WordPress after re-including the plugin file.
     */
    public static function handleUninstall(): void
    {
        foreach (self::$collectors as $collector) {
            self::unschedule($collector);
            $consent = new ConsentManager($collector->getConsentOptionName());
            $consent->delete();
        }
    }

    /**
     * On plugin activation: opt in by default (first install only) and schedule cron when consent is on.
     */
    public static function activateDefaultConsent(PluginCollectorInterface $collector): void
    {
        $consent = new ConsentManager($collector->getConsentOptionName());

        if (!$consent->isConfigured()) {
            $consent->enable();
        }

        if ($consent->isEnabled()) {
            (new self($collector))->enableScheduling();
        }
    }

    public static function setConsent(PluginCollectorInterface $collector, bool $enabled): void
    {
        $consent = new ConsentManager($collector->getConsentOptionName());

        if ($enabled === $consent->isEnabled()) {
            return;
        }

        if ($enabled) {
            $consent->enable();
            (new self($collector))->enableScheduling();
        } else {
            $consent->disable();
            self::unschedule($collector);
        }

    }

    public static function isConsentEnabled(PluginCollectorInterface $collector): bool
    {
        return (new ConsentManager($collector->getConsentOptionName()))->isEnabled();
    }

    /**
     * @return array<string, mixed>
     */
    public static function getSettings(PluginCollectorInterface $collector): array
    {
        return [
            'usageTrackingEnabled' => self::isConsentEnabled($collector),
        ];
    }

    /**
     * Applies all library-managed settings from the incoming array
     * and removes the handled keys so they don't leak into plugin storage.
     */
    public static function updateSettings(PluginCollectorInterface $collector, array &$settings): void
    {
        if (array_key_exists('usageTrackingEnabled', $settings)) {
            self::setConsent($collector, (bool) $settings['usageTrackingEnabled']);
            unset($settings['usageTrackingEnabled']);
        }
    }

    public function boot(): void
    {
        $hook = $this->collector->getCronHookName();

        add_action($hook, [$this, 'send']);

        if ($this->consentManager->isEnabled()) {
            $this->enableScheduling();
        }
    }

    public function enableScheduling(): void
    {
        $hook = $this->collector->getCronHookName();

        if (!wp_next_scheduled($hook)) {
            $this->send();
            wp_schedule_event(time(), $this->collector->getCronSchedule(), $hook);
        }
    }

    public static function unschedule(PluginCollectorInterface $collector): void
    {
        wp_clear_scheduled_hook($collector->getCronHookName());
    }

    public function send(): void
    {
        if (!$this->consentManager->isEnabled()) {
            return;
        }

        $payload = $this->collector->collect();
        $payload = $this->anonymizer->anonymize($payload);

        $this->httpClient->post(self::endpoint(), $payload);
    }

    private static function endpoint(): string
    {
        if (defined('MELOGRANO_BI_GATE_URL')) {
            return MELOGRANO_BI_GATE_URL . '/v1/usage';
        }

        return 'https://bi.melograno.io/v1/usage';
    }
}
