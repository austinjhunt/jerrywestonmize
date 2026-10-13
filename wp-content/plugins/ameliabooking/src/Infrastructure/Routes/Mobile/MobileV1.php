<?php

namespace AmeliaBooking\Infrastructure\Routes\Mobile;

use AmeliaBooking\Application\Controller\Mobile\Appointments\GetAppointmentMobileController;
use AmeliaBooking\Application\Controller\Mobile\Appointments\GetAppointmentsMobileController;
use AmeliaBooking\Application\Controller\Mobile\Appointments\UpdateAppointmentStatusMobileController;
use AmeliaBooking\Application\Controller\Mobile\Entities\GetEntitiesMobileController;
use AmeliaBooking\Application\Controller\Mobile\Devices\RegisterMobileDeviceMobileController;
use AmeliaBooking\Application\Controller\Mobile\Devices\UnregisterMobileDeviceMobileController;
use AmeliaBooking\Application\Controller\Mobile\Events\GetEventMobileController;
use AmeliaBooking\Application\Controller\Mobile\Events\GetEventsMobileController;
use AmeliaBooking\Application\Controller\Mobile\Events\ScanEventTicketMobileController;
use AmeliaBooking\Application\Controller\Mobile\GetMobileInfoController;
use AmeliaBooking\Application\Controller\Mobile\Settings\GetMobileSettingsMobileController;
use AmeliaBooking\Application\Controller\Mobile\Users\GetCustomersMobileController;
use AmeliaBooking\Application\Controller\Mobile\LoginMobileController;
use AmeliaBooking\Application\Controller\Mobile\NotificationPreferences\GetMobileNotificationPreferencesMobileController;
use AmeliaBooking\Application\Controller\Mobile\NotificationPreferences\UpdateMobileNotificationPreferencesMobileController;
use Slim\App;

/**
 * Dedicated routes for the Amelia Staff mobile app.
 *
 * Authenticated routes always run in cabinet-provider context — the `source`
 * param is never read from the client. A Bearer JWT is required; requests
 * without one receive a 409 `{data: {reauthorize: true}}` response, the same
 * shape as an expired-session error, so the app drives the user back to login.
 * Both are enforced by MobileV1Controller, which those controllers extend.
 *
 * Login is the exception: POST /mobile/v1/authenticate is unauthenticated
 * (LoginMobileController extends the cabinet login controller, not
 * MobileV1Controller) so the app can obtain a JWT without a web reCAPTCHA.
 *
 * Versioned under /mobile/v1/ so the contract can evolve independently of the
 * shared web-cabinet routes (/appointments, /events, /users/authenticate)
 * without breaking either.
 */
class MobileV1
{
    /**
     * @param App $app
     *
     * @throws \InvalidArgumentException
     */
    public static function routes(App $app)
    {
        // Version-negotiation handshake. Intentionally UNVERSIONED and
        // UNAUTHENTICATED (no Bearer token) — it is the contract the app uses to
        // detect version skew, so it must never change shape or require auth.
        // Must ship in the same release as the /mobile/v1/* routes below.
        $app->get('/mobile/info', GetMobileInfoController::class);

        // Unauthenticated — obtains the provider JWT. Not gated by MobileV1Controller.
        $app->post('/mobile/v1/authenticate', LoginMobileController::class);

        $app->get('/mobile/v1/appointments', GetAppointmentsMobileController::class);

        $app->get('/mobile/v1/appointments/{id:[0-9]+}', GetAppointmentMobileController::class);

        $app->post('/mobile/v1/appointments/status/{id:[0-9]+}', UpdateAppointmentStatusMobileController::class);

        $app->get('/mobile/v1/events', GetEventsMobileController::class);

        $app->get('/mobile/v1/events/{id:[0-9]+}', GetEventMobileController::class);

        $app->get('/mobile/v1/entities', GetEntitiesMobileController::class);

        $app->get('/mobile/v1/customers', GetCustomersMobileController::class);

        $app->post('/mobile/v1/events/scan', ScanEventTicketMobileController::class);

        $app->get('/mobile/v1/settings', GetMobileSettingsMobileController::class);

        $app->post('/mobile/v1/devices', RegisterMobileDeviceMobileController::class);

        $app->delete('/mobile/v1/devices', UnregisterMobileDeviceMobileController::class);

        $app->get('/mobile/v1/notification-preferences', GetMobileNotificationPreferencesMobileController::class);

        $app->put('/mobile/v1/notification-preferences', UpdateMobileNotificationPreferencesMobileController::class);
    }
}
