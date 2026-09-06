<?php

namespace AmeliaBooking\Application\Commands;

use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Commands\Booking\Appointment\AddBookingCommand;
use AmeliaBooking\Application\Commands\Booking\Appointment\DeleteBookingRemotelyCommand;
use AmeliaBooking\Application\Commands\Booking\Appointment\SuccessfulBookingCommand;
use AmeliaBooking\Application\Commands\Coupon\GetValidCouponCommand;
use AmeliaBooking\Application\Commands\Google\FetchAccessTokenWithAuthCodeCommand;
use AmeliaBooking\Application\Commands\Google\GetGoogleAuthURLCommand;
use AmeliaBooking\Application\Commands\Notification\SendUndeliveredNotificationsCommand;
use AmeliaBooking\Application\Commands\Notification\UpdateSMSNotificationHistoryCommand;
use AmeliaBooking\Application\Commands\Notification\WhatsAppWebhookCommand;
use AmeliaBooking\Application\Commands\Notification\WhatsAppWebhookRegisterCommand;
use AmeliaBooking\Application\Commands\Outlook\FetchAccessTokenWithAuthCodeOutlookCommand;
use AmeliaBooking\Application\Commands\Payment\CalculatePaymentAmountCommand;
use AmeliaBooking\Application\Commands\Payment\PaymentCallbackCommand;
use AmeliaBooking\Application\Commands\Payment\PaymentLinkCommand;
use AmeliaBooking\Application\Commands\PaymentGateway\BarionPaymentCallbackCommand;
use AmeliaBooking\Application\Commands\PaymentGateway\BarionPaymentCommand;
use AmeliaBooking\Application\Commands\PaymentGateway\MolliePaymentCommand;
use AmeliaBooking\Application\Commands\PaymentGateway\MolliePaymentNotifyCommand;
use AmeliaBooking\Application\Commands\PaymentGateway\PayPalPaymentCallbackCommand;
use AmeliaBooking\Application\Commands\PaymentGateway\PayPalPaymentCommand;
use AmeliaBooking\Application\Commands\PaymentGateway\WooCommercePaymentCommand;
use AmeliaBooking\Application\Commands\PaymentGateway\RazorpayPaymentCommand;
use AmeliaBooking\Application\Commands\PaymentGateway\RazorpayPaymentNotifyCommand;
use AmeliaBooking\Application\Commands\Square\DisconnectFromSquareAccountCommand;
use AmeliaBooking\Application\Commands\Square\SquareRefundWebhookCommand;
use AmeliaBooking\Application\Commands\Stripe\CancelStripePaymentIntentCommand;
use AmeliaBooking\Application\Commands\Stripe\CompleteStripePaymentIntentCommand;
use AmeliaBooking\Application\Commands\Stripe\CreateStripePaymentIntentCommand;
use AmeliaBooking\Application\Commands\Stripe\StripePaymentCallbackCommand;
use AmeliaBooking\Application\Commands\User\Customer\ReauthorizeCommand;
use AmeliaBooking\Application\Commands\User\LoginCabinetCommand;
use AmeliaBooking\Application\Commands\User\LogoutCabinetCommand;
use AmeliaBooking\Application\Commands\User\SocialLoginCommand;
use AmeliaBooking\Application\Services\User\UserApplicationService;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Common\Exceptions\AuthorizationException;
use AmeliaBooking\Domain\Services\Permissions\PermissionsService;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\WP\SettingsService\SettingsStorage;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class Command
 *
 * @package AmeliaBooking\Application\Commands
 */
abstract class Command
{
    protected $args;

    protected $container;

    private $fields = [];

    public $token;

    private $page;

    private $cabinetType;

    private $permissionService;

    private $userApplicationService;

    /**
     * Command constructor.
     *
     * @param $args
     */
    public function __construct($args)
    {
        $this->args = $args;
        if (isset($args['type'])) {
            $this->setField('type', $args['type']);
        }
    }

    /**
     * @return mixed
     */
    public function getArgs()
    {
        return $this->args;
    }

    /**
     * @param mixed $arg Argument to be fetched
     *
     * @return null|mixed
     */
    public function getArg($arg)
    {
        return isset($this->args[$arg]) ? $this->args[$arg] : null;
    }

    /**
     * @param $fieldName
     * @param $fieldValue
     */
    public function setField($fieldName, $fieldValue)
    {
        $this->fields[$fieldName] = $fieldValue;
    }

    /**
     * @param $fieldName
     */
    public function removeField($fieldName)
    {
        unset($this->fields[$fieldName]);
    }

    /**
     * Return a single field
     *
     * @param $fieldName
     *
     * @return mixed|null
     */
    public function getField($fieldName)
    {
        return isset($this->fields[$fieldName]) ? $this->fields[$fieldName] : null;
    }

    /**
     * Return all fields
     *
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Set Token
     *
     * @param Request $request
     */
    public function setToken($request): void
    {
        $token = null;

        /** @var SettingsService $settingsService */
        $settingsService = new SettingsService(new SettingsStorage());

        $authorization = $request->getHeaderLine('Authorization');

        if (
            $authorization !== '' &&
            ($values = explode(' ', $authorization)) &&
            sizeof($values) === 2 &&
            strcasecmp($values[0], 'Bearer') === 0 &&
            $settingsService->getSetting('roles', 'enabledHttpAuthorization')
        ) {
            $token = $values[1];
        } else {
            $cookies = $request->getCookieParams();
            if (!empty($cookies['ameliaToken'])) {
                $token = $cookies['ameliaToken'];
            }
        }

        $this->token = $token;
    }

    /**
     * Return Token
     *
     * @return string|null
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Set page
     *
     * @param string $page
     */
    public function setPage($page)
    {
        $this->page = explode('-', $page)[0];

        $this->cabinetType = !empty(explode('-', $page)[1]) ? explode('-', $page)[1] : null;
    }

    /**
     * Return page
     *
     * @return string|null
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @param $request
     * @return bool
     */
    public function validateNonce($request): bool
    {
        if (
            $request->getMethod() === 'POST' &&
            !self::getToken() &&
            !($this instanceof CalculatePaymentAmountCommand) &&
            !($this instanceof ReauthorizeCommand) &&
            !($this instanceof LoginCabinetCommand) &&
            !($this instanceof LogoutCabinetCommand) &&
            !($this instanceof AddBookingCommand) &&
            !($this instanceof DeleteBookingRemotelyCommand) &&
            !($this instanceof SuccessfulBookingCommand) &&
            !($this instanceof GetValidCouponCommand) &&
            !($this instanceof MolliePaymentCommand) &&
            !($this instanceof MolliePaymentNotifyCommand) &&
            !($this instanceof PayPalPaymentCommand) &&
            !($this instanceof PayPalPaymentCallbackCommand) &&
            !($this instanceof RazorpayPaymentCommand) &&
            !($this instanceof RazorpayPaymentNotifyCommand) &&
            !($this instanceof BarionPaymentCommand) &&
            !($this instanceof BarionPaymentCallbackCommand) &&
            !($this instanceof SquareRefundWebhookCommand) &&
            !($this instanceof DisconnectFromSquareAccountCommand) &&
            !($this instanceof WooCommercePaymentCommand) &&
            !($this instanceof PaymentCallbackCommand) &&
            !($this instanceof GetGoogleAuthURLCommand) &&
            !($this instanceof FetchAccessTokenWithAuthCodeOutlookCommand) &&
            !($this instanceof FetchAccessTokenWithAuthCodeCommand) &&
            !($this instanceof WhatsAppWebhookRegisterCommand) &&
            !($this instanceof WhatsAppWebhookCommand) &&
            !($this instanceof PaymentLinkCommand) &&
            !($this instanceof SocialLoginCommand) &&
            !($this instanceof CreateStripePaymentIntentCommand) &&
            !($this instanceof CancelStripePaymentIntentCommand) &&
            !($this instanceof CompleteStripePaymentIntentCommand) &&
            !($this instanceof StripePaymentCallbackCommand) &&
            !($this instanceof UpdateSMSNotificationHistoryCommand)
        ) {
            $queryParams = $request->getQueryParams();
            $nonce = !empty($queryParams['wpAmeliaNonce'])
                ? $queryParams['wpAmeliaNonce']
                : ($queryParams['ameliaNonce'] ?? '');

            return (bool) wp_verify_nonce(
                $nonce,
                'ajax-nonce'
            );
        }

        return true;
    }

    /**
     * Commands listed here are meant to be run from an external cron job, so they are reached with a GET request that
     * carries neither a nonce nor a logged in user. They are protected with a secret key, generated per site and
     * stored in the "activation" settings, that has to be passed as a "cronKey" query parameter.
     *
     * @param $request
     * @return boolean
     */
    public function validateCron($request)
    {
        if (!($this instanceof SendUndeliveredNotificationsCommand)) {
            return true;
        }

        $settingsService = new SettingsService(new SettingsStorage());

        $cronKey = $settingsService->getSetting('activation', 'cronKey');

        if (empty($cronKey)) {
            return false;
        }

        $queryParams = $request->getQueryParams();

        $givenKey = !empty($queryParams['cronKey']) ? $queryParams['cronKey'] : '';

        return is_string($givenKey) && hash_equals((string)$cronKey, $givenKey);
    }

    /**
     * Return cabinet type
     *
     * @return string|null
     */
    public function getCabinetType()
    {
        return $this->cabinetType;
    }

    /**
     * @return PermissionsService
     */
    public function getPermissionService()
    {
        return $this->permissionService;
    }

    /**
     * @param PermissionsService $permissionService
     */
    public function setPermissionService($permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * @return UserApplicationService
     */
    public function getUserApplicationService()
    {
        return $this->userApplicationService;
    }

    /**
     * @param UserApplicationService $userApplicationService
     */
    public function setUserApplicationService($userApplicationService)
    {
        $this->userApplicationService = $userApplicationService;
    }

    /**
     * Authorize user
     *
     * @return AbstractUser
     * @throws AuthorizationException
     * @throws AccessDeniedException
     */
    public function authorize($type = null): AbstractUser
    {
        if ($type === AbstractUser::USER_ROLE_PROVIDER || $type === AbstractUser::USER_ROLE_CUSTOMER) {
            /** @var AbstractUser $user */
            $user = $this->getUserApplicationService()->authorization(
                $this->getToken(),
                $type
            );

            // If user is admin or manager, return user
            if (
                $user && (
                    $user->getType() === AbstractUser::USER_ROLE_ADMIN ||
                    $user->getType() === AbstractUser::USER_ROLE_MANAGER
                )
            ) {
                return $user;
            }

            // If user is not admin or manager, check if user is of the given type
            if (!$user || $user->getType() !== $type) {
                throw new AccessDeniedException('You are not allowed');
            }

            return $user;
        }

        return $this->getUserApplicationService()->authorization(
            $this->getPage() === 'cabinet' ? $this->getToken() : null,
            $this->getCabinetType()
        );
    }

    /**
     * Authorize provider read permission
     *
     * @param int    $userId
     * @param string $entity
     *
     * @return AbstractUser
     *
     * @throws AuthorizationException
     * @throws AccessDeniedException
     */
    public function authorizeProviderReadPermission(int $userId, string $entity = Entities::EMPLOYEES): AbstractUser
    {
        // if logged in user is not WP admin, WP Amelia manager or WP Amelia provider, try to authorize as non WP Amelia provider
        if (
            !$this->getPermissionService()->currentUserCanRead($entity) ||
            !$this->getPermissionService()->currentUserCanReadOthers($entity)
        ) {
            /** @var AbstractUser $user */
            $user = $this->authorize(Entities::PROVIDER);

            // if authorized as non WP Amelia provider, check if user ID is the same as the requested user ID
            if (
                $user->getType() === AbstractUser::USER_ROLE_PROVIDER &&
                $user->getId()->getValue() !== $userId
            ) {
                throw new AccessDeniedException('You are not allowed');
            }

            return $user;
        }

        return $this->getUserApplicationService()->authorization(null, null);
    }

    /**
     * Authorize provider write permission
     *
     * @param int    $userId
     * @param string $entity
     *
     * @return AbstractUser
     *
     * @throws AuthorizationException
     * @throws AccessDeniedException
     */
    public function authorizeProviderWritePermission(int $userId, string $entity = Entities::EMPLOYEES): AbstractUser
    {
        // if logged in user is not WP admin, WP Amelia manager or WP Amelia provider, try to authorize as non WP Amelia provider
        if (
            !$this->getPermissionService()->currentUserCanWrite($entity) ||
            !$this->getPermissionService()->currentUserCanWriteOthers($entity)
        ) {
            /** @var AbstractUser $user */
            $user = $this->authorize(Entities::PROVIDER);

            // if authorized as non WP Amelia provider, check if user ID is the same as the requested user ID
            if (
                $user->getType() === AbstractUser::USER_ROLE_PROVIDER &&
                $user->getId()->getValue() !== $userId
            ) {
                throw new AccessDeniedException('You are not allowed');
            }

            return $user;
        }

        return $this->getUserApplicationService()->authorization(null, null);
    }

    /**
     * Authorize appointment status write: require write_status capability, or fall
     * through to provider cabinet JWT auth (mobile/employee panel path).
     *
     * @return AbstractUser
     *
     * @throws AuthorizationException
     * @throws AccessDeniedException
     */
    public function authorizeAppointmentStatusWrite(): AbstractUser
    {
        if ($this->getPermissionService()->currentUserCanWriteStatus(Entities::APPOINTMENTS)) {
            return $this->getUserApplicationService()->authorization(
                $this->getPage() === 'cabinet' ? $this->getToken() : null,
                $this->getCabinetType()
            );
        }

        return $this->authorize(Entities::PROVIDER);
    }
}
