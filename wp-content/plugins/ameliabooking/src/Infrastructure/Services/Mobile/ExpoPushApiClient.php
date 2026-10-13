<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\Services\Mobile;

use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Domain\Services\Mobile\MobilePushGatewayInterface;

/**
 * Class ExpoPushApiClient
 *
 * @package AmeliaBooking\Infrastructure\Services\Mobile
 */
class ExpoPushApiClient implements MobilePushGatewayInterface
{
    public const PUSH_API_URL = 'https://exp.host/--/api/v2/push/send';

    public const BATCH_SIZE = 100;

    public const ERROR_DEVICE_NOT_REGISTERED = 'DeviceNotRegistered';

    /** @var LoggerInterface|null */
    private $logger;

    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct($logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     *
     * @return array<int, array<string, mixed>>
     */
    public function send(array $messages)
    {
        if (empty($messages)) {
            return [];
        }

        $results = [];

        foreach (self::batchMessages($messages) as $batch) {
            $results = array_merge($results, $this->sendBatch($batch));
        }

        return $results;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param int                              $batchSize
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function batchMessages(array $messages, $batchSize = self::BATCH_SIZE)
    {
        if ($batchSize < 1) {
            $batchSize = self::BATCH_SIZE;
        }

        $batches = [];
        $batch   = [];

        foreach ($messages as $message) {
            $batch[] = $message;

            if (count($batch) === $batchSize) {
                $batches[] = $batch;
                $batch     = [];
            }
        }

        if (!empty($batch)) {
            $batches[] = $batch;
        }

        return $batches;
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return bool
     */
    public function isStaleTokenResult(array $result)
    {
        if (($result['status'] ?? '') !== 'error') {
            return false;
        }

        $detailsError = isset($result['details']['error']) ? (string) $result['details']['error'] : '';

        if ($detailsError === self::ERROR_DEVICE_NOT_REGISTERED) {
            return true;
        }

        $message = isset($result['message']) ? (string) $result['message'] : '';

        return stripos($message, 'devicenotregistered') !== false;
    }

    /**
     * @param array<int, array<string, mixed>> $batch
     *
     * @return array<int, array<string, mixed>>
     */
    private function sendBatch(array $batch)
    {
        $response = wp_remote_post(
            self::PUSH_API_URL,
            [
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body'    => wp_json_encode($batch),
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            $this->logError(
                'Expo push request failed',
                ['error' => $response->get_error_message()]
            );

            return $this->buildErrorResults($batch, $response->get_error_message());
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $body       = wp_remote_retrieve_body($response);
        $decoded    = json_decode($body, true);

        if ($statusCode < 200 || $statusCode >= 300 || !is_array($decoded)) {
            $this->logError(
                'Expo push returned an invalid response',
                ['statusCode' => $statusCode, 'body' => $body]
            );

            return $this->buildErrorResults($batch, 'Invalid Expo push API response');
        }

        return $this->mapBatchResults($batch, $decoded);
    }

    /**
     * @param array<int, array<string, mixed>> $batch
     * @param array<string, mixed>             $decoded
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapBatchResults(array $batch, array $decoded)
    {
        $tickets = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];
        $results = [];

        foreach ($batch as $index => $message) {
            $ticket  = isset($tickets[$index]) && is_array($tickets[$index]) ? $tickets[$index] : [];
            $results[] = [
                'token'   => isset($message['to']) ? (string) $message['to'] : '',
                'status'  => isset($ticket['status']) ? (string) $ticket['status'] : 'error',
                'id'      => $ticket['id'] ?? null,
                'message' => $ticket['message'] ?? null,
                'details' => isset($ticket['details']) && is_array($ticket['details']) ? $ticket['details'] : [],
            ];
        }

        return $results;
    }

    /**
     * @param array<int, array<string, mixed>> $batch
     * @param string                           $message
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildErrorResults(array $batch, $message)
    {
        $results = [];

        foreach ($batch as $messageItem) {
            $results[] = [
                'token'   => isset($messageItem['to']) ? (string) $messageItem['to'] : '',
                'status'  => 'error',
                'id'      => null,
                'message' => $message,
                'details' => [],
            ];
        }

        return $results;
    }

    /**
     * @param string               $message
     * @param array<string, mixed> $context
     */
    private function logError($message, array $context = [])
    {
        if ($this->logger) {
            $this->logger->error($message, $context);
        }
    }
}
