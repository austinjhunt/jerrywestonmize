<?php

namespace AmeliaBooking\Application\Services\Payment;

use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Services\Payment\StripeService;
use Throwable;

/**
 * Keeps this site's Stripe webhook endpoints registered.
 *
 * The webhook is what finalizes a booking when the customer leaves before the confirmation
 * page, so it must not depend on someone remembering to set it up. Saving the Stripe settings
 * arms it, which is where almost every site gets its endpoint; the payment path runs the same
 * check as a fallback, for the sites a save never covered - a restored database, a moved
 * domain, an endpoint deleted from the Stripe Dashboard.
 *
 * Class StripeWebhookRegistrationService
 *
 * @package AmeliaBooking\Application\Services\Payment
 */
class StripeWebhookRegistrationService
{
    /**
     * Held for the duration of one registration, so two bookings starting at the same moment
     * cannot both create an endpoint.
     */
    public const LOCK_OPTION_PREFIX = 'amelia_stripe_webhook_lock_';

    /**
     * Set after a failed attempt, so a Stripe outage is not retried on every single booking.
     */
    public const BACKOFF_TRANSIENT_PREFIX = 'amelia_stripe_webhook_retry_';

    public const LOCK_TTL_SECONDS = 60;

    /**
     * How long a failure that may pass on its own - a rate limit, an outage - is waited out.
     */
    public const BACKOFF_TRANSIENT_TTL_SECONDS = 900;

    /**
     * A rejection only an admin can act on is not retried on the payment path for a day: no
     * number of bookings turns a revoked key back into a valid one, and every retry is Stripe
     * latency a customer waits through. Saving the Stripe settings clears the wait, so fixing
     * the cause never means sitting it out.
     */
    public const BACKOFF_PERMANENT_TTL_SECONDS = 86400;

    /**
     * Error codes this service raises for a setup that will keep being refused until someone
     * changes it.
     */
    public const PERMANENT_ERRORS = ['invalidSecretKey', 'httpsRequired', 'localUrl', 'invalidUrl'];

    /**
     * Per-mode setting suffixes this service owns. They hold what Stripe handed back when the
     * endpoint was created and are never edited by hand, so the settings form must not be
     * able to write them.
     */
    public const SERVER_OWNED_SETTINGS = [
        'WebhookSecret',
        'WebhookId',
        'WebhookKeyFingerprint',
        'ConnectWebhookSecret',
        'ConnectWebhookId',
    ];

    /** @var Container */
    private $container;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Arms the webhook if this site has no working endpoint yet.
     *
     * Called from the payment path, so it swallows every failure: the safety net not being
     * armed is a degraded state, while a booking refused because Stripe's webhook API was
     * unreachable is a lost sale.
     *
     * @return void
     */
    public function ensureRegistered(): void
    {
        try {
            /** @var SettingsService $settingsService */
            $settingsService = $this->container->get('domain.settings.service');

            $stripeSettings = $settingsService->getSetting('payments', 'stripe');

            if (!is_array($stripeSettings)) {
                return;
            }

            $testMode = !empty($stripeSettings['testMode']);

            if (!self::needsRegistration($stripeSettings, $testMode)) {
                return;
            }

            $secretKey = trim((string)($testMode
                ? (isset($stripeSettings['testSecretKey']) ? $stripeSettings['testSecretKey'] : '')
                : (isset($stripeSettings['liveSecretKey']) ? $stripeSettings['liveSecretKey'] : '')));

            if ($secretKey === '') {
                return;
            }

            /** @var StripeService $stripeService */
            $stripeService = $this->container->get('infrastructure.payment.stripe.service');

            // Local and staging installs are settled without calling Stripe at all: there is
            // no URL to register, and every booking would otherwise pay for finding that out.
            if ($stripeService->getWebhookUrlError($testMode) !== null) {
                return;
            }

            if ($this->isBackingOff($testMode)) {
                return;
            }

            if (!$this->claimLock($testMode)) {
                return;
            }

            try {
                $registration = $stripeService->registerWebhookEndpoints($secretKey, $testMode);

                if (!empty($registration['error']) || empty($registration['account']['secret'])) {
                    // Registration removed the endpoint this site had and then failed to put
                    // one in its place. The stored secret and id now describe nothing, and
                    // needsRegistration() would read them as armed and never try again.
                    if (!empty($registration['account']['deleted'])) {
                        $this->invalidateStoredWebhook($testMode);
                    }

                    $permanent = self::isPermanentFailure($registration);

                    $this->startBackoff($testMode, $permanent);
                    $this->log(
                        'error',
                        'Amelia Stripe: webhook could not be registered automatically',
                        [
                            'error'   => self::firstNonEmpty(
                                [
                                    !empty($registration['error']) ? $registration['error'] : '',
                                    !empty($registration['account']['error']) ? $registration['account']['error'] : '',
                                ],
                                'requestFailed'
                            ),
                            'message' => self::firstNonEmpty(
                                [
                                    !empty($registration['account']['message'])
                                        ? $registration['account']['message'] : '',
                                    !empty($registration['message']) ? $registration['message'] : '',
                                ],
                                ''
                            ),
                            'retry'   => $permanent ? 'awaitingSettingsChange' : 'scheduled',
                        ]
                    );

                    return;
                }

                $this->store($stripeSettings, $testMode, $registration, $secretKey);

                // The mode is put right, so the wait its last failure earned is over.
                $this->clearBackoff($testMode);
            } finally {
                $this->releaseLock($testMode);
            }
        } catch (Throwable $e) {
            $this->log('error', 'Amelia Stripe: automatic webhook registration failed', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Registers the webhook right after an admin saved the Stripe settings.
     *
     * This is where a site is normally armed. A save is also the admin acting on whatever the
     * last failure was about, so any wait that failure set is dropped first: a corrected key,
     * or a Connect account that has since been set up, must not have to sit out hours of
     * backoff.
     *
     * @return void
     */
    public function ensureRegisteredAfterSettingsSave(): void
    {
        try {
            $this->clearBackoff(false);
            $this->clearBackoff(true);

            $this->ensureRegistered();
        } catch (Throwable $e) {
            $this->log(
                'error',
                'Amelia Stripe: webhook registration after a settings save failed',
                ['exception' => $e->getMessage()]
            );
        }
    }

    /**
     * Whether this mode still needs an endpoint registered for it.
     *
     * A secret the customer pasted in by hand is never replaced: without an endpoint id
     * beside it, there is no evidence Amelia created that endpoint, and re-registering would
     * delete a working one the customer set up in the Stripe Dashboard.
     *
     * @param array $stripeSettings
     * @param bool  $testMode
     *
     * @return bool
     */
    public static function needsRegistration(array $stripeSettings, $testMode): bool
    {
        $prefix = $testMode ? 'test' : 'live';

        $get = function ($key) use ($stripeSettings) {
            return isset($stripeSettings[$key]) ? trim((string)$stripeSettings[$key]) : '';
        };

        if ($get($prefix . 'WebhookSecret') === '') {
            return true;
        }

        if ($get($prefix . 'WebhookId') === '') {
            return false;
        }

        $fingerprint = $get($prefix . 'WebhookKeyFingerprint');
        $secretKey   = $get($prefix . 'SecretKey');

        if ($fingerprint === '' || $secretKey === '') {
            return false;
        }

        // The endpoint lives on the account the key belonged to. A different key can mean a
        // different account, whose deliveries the stored secret could never verify.
        return !hash_equals($fingerprint, self::keyFingerprint($secretKey));
    }

    /**
     * @param string $secretKey
     *
     * @return string
     */
    public static function keyFingerprint($secretKey): string
    {
        return hash('sha256', (string)$secretKey);
    }

    /**
     * Drops this mode's stored endpoint id, secret and fingerprint.
     *
     * Used when the endpoint they describe is known to be gone, so that the next attempt
     * sees a mode with no webhook rather than one that only looks registered.
     *
     * @param bool $testMode
     *
     * @return void
     */
    private function invalidateStoredWebhook($testMode): void
    {
        $prefix = $testMode ? 'test' : 'live';

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $currentSettings = $settingsService->getSetting('payments', 'stripe');

        if (!is_array($currentSettings)) {
            return;
        }

        $currentSettings[$prefix . 'WebhookSecret']         = '';
        $currentSettings[$prefix . 'WebhookId']             = '';
        $currentSettings[$prefix . 'WebhookKeyFingerprint'] = '';

        $settingsService->setSetting('payments', 'stripe', $currentSettings);
    }

    /**
     * @param array  $stripeSettings
     * @param bool   $testMode
     * @param array  $registration
     * @param string $secretKey
     *
     * @return void
     */
    private function store(array $stripeSettings, $testMode, array $registration, string $secretKey): void
    {
        $prefix = $testMode ? 'test' : 'live';

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        // Re-read immediately before persist: registerWebhookEndpoints() can take
        // long enough for an admin save to change keys, enabled, or Connect.
        $currentSettings = $settingsService->getSetting('payments', 'stripe');

        if (!is_array($currentSettings)) {
            $currentSettings = $stripeSettings;
        }

        foreach (self::serverOwnedValues($registration, $secretKey) as $suffix => $value) {
            $currentSettings[$prefix . $suffix] = $value;
        }

        $settingsService->setSetting('payments', 'stripe', $currentSettings);
    }

    /**
     * Overlays the stored server-owned values onto submitted settings, so a save cannot write
     * them.
     *
     * The settings form cannot edit these values, so what it submits is a copy taken when the
     * page loaded; the stored ones have to win, or the settings would claim a webhook is
     * armed while the secret that verifies its deliveries is gone.
     *
     * @param array $settingsFields
     *
     * @return array
     */
    public function withServerOwnedSettings(array $settingsFields): array
    {
        if (
            !isset($settingsFields['payments']['stripe']) ||
            !is_array($settingsFields['payments']['stripe'])
        ) {
            return $settingsFields;
        }

        $savedSettings = self::storedStripeSettings();

        foreach (self::SERVER_OWNED_SETTINGS as $suffix) {
            foreach (['live', 'test'] as $prefix) {
                $key = $prefix . $suffix;

                $settingsFields['payments']['stripe'][$key] = isset($savedSettings[$key])
                    ? $savedSettings[$key]
                    : '';
            }
        }

        return $settingsFields;
    }

    /**
     * What a completed registration writes, keyed by the mode-independent setting suffix.
     *
     * Every key this returns is a key the settings form is not allowed to write, so it may
     * only contain suffixes listed in SERVER_OWNED_SETTINGS - otherwise a saved settings form
     * would quietly undo part of a registration.
     *
     * @param array  $registration
     * @param string $secretKey
     *
     * @return array
     */
    public static function serverOwnedValues(array $registration, string $secretKey): array
    {
        $account = !empty($registration['account']) ? $registration['account'] : [];
        $connect = !empty($registration['connect']) ? $registration['connect'] : [];

        $values = [
            'WebhookSecret'         => !empty($account['secret']) ? (string)$account['secret'] : '',
            'WebhookId'             => !empty($account['id']) ? (string)$account['id'] : '',
            'WebhookKeyFingerprint' => self::keyFingerprint($secretKey),
        ];

        // A Connect endpoint that could not be created leaves the stored one untouched: it
        // may still be a working endpoint from an earlier registration, and a failure here
        // is no reason to stop verifying the deliveries it signs.
        if (!empty($connect['secret'])) {
            $values['ConnectWebhookSecret'] = (string)$connect['secret'];
            $values['ConnectWebhookId']     = !empty($connect['id']) ? (string)$connect['id'] : '';
        }

        return array_intersect_key($values, array_flip(self::SERVER_OWNED_SETTINGS));
    }

    /**
     * The stored Stripe settings, read straight from the option.
     *
     * Not through SettingsService: its cache is a snapshot taken when the request started, so
     * a registration that finished after that - on the payment path, in another process - is
     * invisible to it. A save reading that snapshot would write the empty value it still holds
     * over the secret that verifies live deliveries, and the endpoint would keep delivering
     * events this site can no longer verify.
     *
     * @return array
     */
    private static function storedStripeSettings(): array
    {
        $stored = json_decode((string)get_option('amelia_settings'), true);

        return !empty($stored['payments']['stripe']) && is_array($stored['payments']['stripe'])
            ? $stored['payments']['stripe']
            : [];
    }

    /**
     * The lock and the backoff below reach for the WordPress option and transient APIs rather
     * than SettingsService: the claim is add_option's insert-or-fail, and a lock read through
     * a settings layer that may cache or merge is not a lock.
     *
     * A lease whose holder died is taken over once it expires. Taking over is not atomic, so
     * in the worst case two endpoints are created for the same URL; the next registration
     * removes the one that is not stored.
     *
     * @param bool $testMode
     *
     * @return bool Whether this request may register
     */
    private function claimLock($testMode): bool
    {
        $key = self::LOCK_OPTION_PREFIX . ($testMode ? 'test' : 'live');

        // add_option inserts and reports failure when the row already exists, which is what
        // makes it usable as the claim itself.
        if (add_option($key, time(), '', false)) {
            return true;
        }

        $heldSince = (int)get_option($key);

        if ($heldSince > 0 && (time() - $heldSince) < self::LOCK_TTL_SECONDS) {
            return false;
        }

        update_option($key, time(), false);

        return true;
    }

    /**
     * @param bool $testMode
     *
     * @return void
     */
    private function releaseLock($testMode): void
    {
        delete_option(self::LOCK_OPTION_PREFIX . ($testMode ? 'test' : 'live'));
    }

    /**
     * @param bool $testMode
     *
     * @return bool
     */
    private function isBackingOff($testMode): bool
    {
        return (bool)get_transient(self::BACKOFF_TRANSIENT_PREFIX . ($testMode ? 'test' : 'live'));
    }

    /**
     * Holds this mode off the payment path until the wait its failure earned has passed.
     *
     * @param bool $testMode
     * @param bool $permanent Whether retrying could ever produce a different result
     *
     * @return void
     */
    private function startBackoff($testMode, bool $permanent): void
    {
        set_transient(
            self::BACKOFF_TRANSIENT_PREFIX . ($testMode ? 'test' : 'live'),
            1,
            $permanent ? self::BACKOFF_PERMANENT_TTL_SECONDS : self::BACKOFF_TRANSIENT_TTL_SECONDS
        );
    }

    /**
     * @param bool $testMode
     *
     * @return void
     */
    private function clearBackoff($testMode): void
    {
        delete_transient(self::BACKOFF_TRANSIENT_PREFIX . ($testMode ? 'test' : 'live'));
    }

    /**
     * Whether retrying this failure on a later booking could ever produce a different result.
     *
     * Stripe answers a revoked key, an account without the permission, or an endpoint limit
     * that is already full with a 4xx, and it will answer the next booking the same way until
     * someone changes something - all a short retry adds there is Stripe latency inside a
     * customer's payment. Too many requests, and everything from 500 up, is the opposite:
     * nothing about the setup is wrong, so those keep the short wait.
     *
     * @param array $registration
     *
     * @return bool
     */
    public static function isPermanentFailure(array $registration): bool
    {
        $sources = [$registration, !empty($registration['account']) ? $registration['account'] : []];

        foreach ($sources as $source) {
            if (!empty($source['error']) && in_array($source['error'], self::PERMANENT_ERRORS, true)) {
                return true;
            }

            $status = (int)(!empty($source['httpStatus']) ? $source['httpStatus'] : 0);

            if ($status >= 400 && $status < 500 && $status !== 429) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array  $candidates
     * @param string $fallback
     *
     * @return string
     */
    private static function firstNonEmpty(array $candidates, string $fallback): string
    {
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && $candidate !== null) {
                return (string)$candidate;
            }
        }

        return $fallback;
    }

    /**
     * @param string $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    private function log($level, $message, array $context): void
    {
        try {
            $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)
                ->{$level}($message, $context);
        } catch (Throwable $e) {
            // Logging must not be the reason a payment path fails.
        }
    }
}
