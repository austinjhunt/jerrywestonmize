<?php

namespace AmeliaBooking\Infrastructure\Services\Logger;

use AmeliaVendor\Monolog\Formatter\FormatterInterface;
use DateTimeInterface;

/**
 * Class JsonLineFormatter
 *
 * Formats a Monolog record as a single JSON-line:
 * {"timestamp":"...","level":"ERROR","message":"...","context":{"channel":"payment","request_id":"...","order_id":42}}
 *
 * @package AmeliaBooking\Infrastructure\Services\Logger
 */
class JsonLineFormatter implements FormatterInterface
{
    private const ENCODE_FLAGS = JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

    /**
     * @param mixed[] $record
     *
     * @return string
     */
    public function format(array $record)
    {
        $datetime = $record['datetime'] ?? null;
        $timestamp = $datetime instanceof DateTimeInterface
            ? $datetime->format(DATE_ATOM)
            : gmdate(DATE_ATOM);

        // Caller context first, then processor extras, then force the Monolog channel last
        // so caller-supplied keys cannot overwrite trusted channel metadata.
        $context = array_merge(
            is_array($record['context'] ?? null) ? $record['context'] : [],
            is_array($record['extra'] ?? null) ? $record['extra'] : [],
            ['channel' => $record['channel'] ?? 'app']
        );

        $payload = [
            'timestamp' => $timestamp,
            'level'     => strtoupper((string) ($record['level_name'] ?? 'INFO')),
            'message'   => (string) ($record['message'] ?? ''),
            'context'   => $context,
        ];

        $encoded = json_encode($payload, self::ENCODE_FLAGS);

        if ($encoded === false) {
            $encoded = '{"timestamp":"' . $timestamp . '","level":"ERROR","message":"log_encode_failed","context":{}}';
        }

        return $encoded . "\n";
    }

    /**
     * @param mixed[] $records
     *
     * @return string
     */
    public function formatBatch(array $records)
    {
        $formatted = '';

        foreach ($records as $record) {
            $formatted .= $this->format($record);
        }

        return $formatted;
    }
}
