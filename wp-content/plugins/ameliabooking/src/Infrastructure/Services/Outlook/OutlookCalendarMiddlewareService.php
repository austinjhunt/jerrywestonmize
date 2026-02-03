<?php

namespace AmeliaBooking\Infrastructure\Services\Outlook;

use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaVendor\GuzzleHttp\Exception\GuzzleException;
use Exception;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\Calendar;
use Microsoft\Graph\Model\FileAttachment;
use Microsoft\Graph\Model\Message;

class OutlookCalendarMiddlewareService extends AbstractOutlookCalendarMiddlewareService
{
    private const OUTLOOK_USER_INFO_URL = 'https://graph.microsoft.com/oidc/userinfo';
    private $graph;
    private $outlookCalendarSettings;
    private $service;
    private $outlookUserInfoUrl;

    /**
     * OutlookCalendarMiddlewareService constructor.
     *
     * @param SettingsService $settingsService
     */
    public function __construct(SettingsService $settingsService)
    {
        $this->service = $settingsService;
        $this->outlookCalendarSettings = $settingsService->getCategorySettings('outlookCalendar');
        $this->graph = new Graph();
        $this->outlookUserInfoUrl = self::OUTLOOK_USER_INFO_URL;
    }

    public function getAuthUrl(?int $providerId, ?string $returnUrl, bool $isBackend): ?string
    {
        $ajaxUrl = admin_url('admin-ajax.php', '');
        $params = [
            'admin_ajax_url' => $ajaxUrl,
            'providerId' => $providerId,
            'returnUrl' => $returnUrl,
            'isBackend' => $isBackend,
        ];

        $url = AMELIA_MIDDLEWARE_URL . 'outlook/authorization/url?' . http_build_query($params);

        $ch = curl_init($url);

        // Check if curl initialization failed
        if ($ch === false) {
            error_log('OutlookCalendar: Failed to initialize curl for URL: ' . $url);
            return null;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        $url = null;
        if ($response) {
            $response = json_decode($response, true);
            $url      = $response['result'];
        }

        curl_close($ch);

        return $url;
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function getCalendarList(array $providerOutlookCalendar): array
    {
        $calendars = [];
        $this->authorizeProvider($providerOutlookCalendar);

        try {
            $outlookCalendars = $this->graph
                ->createCollectionRequest('GET', '/me/calendars')
                ->setReturnType(Calendar::class)
                ->setPageSize(100)
                ->getPage();

            /** @var Calendar $outlookCalendar */
            foreach ($outlookCalendars as $outlookCalendar) {
                if ($outlookCalendar->getCanEdit()) {
                    $calendars[] = [
                        'id' => $outlookCalendar->getId(),
                        'name' => $outlookCalendar->getName(),
                        'owner' => $outlookCalendar->getOwner() ? $outlookCalendar->getOwner()->getName() : null,
                    ];
                }
            }
        } catch (GraphException $e) {
            throw new Exception('Failed to fetch Outlook calendars: ' . $e->getMessage());
        }

        return $calendars;
    }

    /**
     * @param array|null $provider
     * @throws Exception
     */
    public function authorizeProvider(array $provider = null): void
    {
        $graph = $this->getGraph($provider);

        if (!$graph) {
            throw new Exception('No access token found for Outlook Calendar.');
        }

        $this->graph = $graph;
    }

    /**
     * @param array $token
     *
     * @return bool
     */
    private function isAccessTokenExpired(array $token): bool
    {
        if (!isset($token['created'])) {
            return true;
        }

        return ($token['created'] + ($token['expires_in'] - 30)) < time();
    }

    /**
     * Get configured Graph client with the appropriate token (provider-specific or global)
     *
     * @param array|null $providerOutlookCalendar
     * @return Graph|null
     * @throws Exception
     */
    public function getGraph(array $providerOutlookCalendar = null): ?Graph
    {
        $accessTokenJson = $this->outlookCalendarSettings['accessToken'] ?? null;

        if ($providerOutlookCalendar && isset($providerOutlookCalendar['token'])) {
            $accessTokenJson = $providerOutlookCalendar['token'];
        }

        if (!$accessTokenJson) {
            error_log('OutlookCalendar: No access token available');
            return null;
        }

        $accessToken = json_decode($accessTokenJson, true);
        if (!$accessToken) {
            error_log('OutlookCalendar: Failed to decode access token');
            return null;
        }

        $graph = new Graph();

        if ($this->isAccessTokenExpired($accessToken)) {
            $refreshToken = $accessToken['refresh_token'] ?? null;
            $newAccessToken = $refreshToken ? $this->getRefreshAccessToken($refreshToken) : null;

            if (!$newAccessToken) {
                error_log('OutlookCalendar: Failed to refresh access token');
                return null;
            }

            // Only update global settings if not using provider-specific token
            if (!$providerOutlookCalendar || !isset($providerOutlookCalendar['token'])) {
                $this->outlookCalendarSettings['accessToken'] = json_encode($newAccessToken);
                $this->service->setCategorySettings('outlookCalendar', $this->outlookCalendarSettings);
            }

            $graph->setAccessToken($newAccessToken['access_token']);
        } else {
            $graph->setAccessToken($accessToken['access_token']);
        }

        return $graph;
    }

    /**
     * Refresh access token and return the new token array
     *
     * @param string $refreshToken
     * @return array|null
     */
    private function getRefreshAccessToken(string $refreshToken): ?array
    {
        $endpoint = AMELIA_MIDDLEWARE_URL . 'outlook/authorization/refresh';

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode(['refresh_token' => $refreshToken])),
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['refresh_token' => $refreshToken]));

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $responseData = json_decode($response, true);
            $result = $responseData['result'] ?? null;
            if (isset($result['access_token'])) {
                $result['created'] = time();
                return $result;
            }
        }

        return null;
    }

    /**
     * @param string $accessToken
     * @return array
     */
    public function getUserInfo(string $accessToken): array
    {
        $token = json_decode($accessToken, true);

        $url = $this->outlookUserInfoUrl;

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . ($token['access_token'] ?? ''),
                'Accept'        => 'application/json',
            ],
            'timeout' => 15,
        ];

        $response = wp_remote_get($url, $args);

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return [
            'email' => $data['email'] ?? $data['preferred_username'] ?? null,
            'name'  => $data['name'] ?? null,
            'picture' => $data['picture'] ?? null
        ];
    }

    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
     * @param string $from
     * @param string $fromName
     * @param string $replyTo
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param array $bccEmails
     * @param array $attachments
     *
     * @return void
     *
     * @throws GraphException
     * @throws Exception
     */
    public function sendEmail(
        string $from,
        string $fromName,
        string $replyTo,
        string $to,
        string $subject,
        string $body,
        array $bccEmails = [],
        array $attachments = []
    ) {
        $this->authorizeProvider();

        $attachmentList = [];

        foreach ($attachments as $attachment) {
            $attachmentObject = new FileAttachment();

            $attachmentObject->setODataType("#microsoft.graph.fileAttachment");
            $attachmentObject->setName($attachment['fileName']);
            $attachmentObject->setContentType(mime_content_type($attachment['filePath']));
            $attachmentObject->setContentBytes(base64_encode(file_get_contents($attachment['filePath'])));
            $attachmentObject->setIsInline(false);

            $attachmentList[] = $attachmentObject;
        }

        $bccList = [];

        foreach ($bccEmails as $bcc) {
            $bccList[] = [
                'emailAddress' => [
                    'address' => trim($bcc),
                ]
            ];
        }

        $message = new Message(
            [
                'from'          => [
                    'emailAddress' => [
                        'name' => mb_convert_encoding($fromName, 'UTF-8'),
                    ]
                ],
                'subject'       => mb_convert_encoding($subject, 'UTF-8'),
                'body'          => [
                    'contentType' => 'HTML',
                    'content'     => mb_convert_encoding($body, 'UTF-8'),
                ],
                'toRecipients'  => [
                    [
                        'emailAddress' => [
                            'address' => trim($to),
                        ],
                    ]
                ],
                'replyTo'       => trim($replyTo) ? [
                    [
                        'emailAddress' => [
                            'address' => trim($replyTo),
                        ],
                    ]
                ] : [],
                'bccRecipients' => $bccList,
                'attachments'   => $attachmentList,
            ]
        );

        try {
            $this->graph
                ->createRequest('POST', '/me/sendMail')
                ->attachBody(
                    [
                        'message'         => $message,
                        'saveToSentItems' => true
                    ]
                )
                ->execute();
        } catch (\Exception $e) {
            error_log('OutlookCalendar: Failed to send email - ' . $e->getMessage());
            throw new Exception('Failed to send email via Outlook: ' . $e->getMessage());
        }
    }
}
