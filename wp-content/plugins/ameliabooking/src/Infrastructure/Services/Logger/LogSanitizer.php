<?php

namespace AmeliaBooking\Infrastructure\Services\Logger;

/**
 * Class LogSanitizer
 *
 * Redacts known sensitive keys and masks emails / phone numbers in message / context / extra before write.
 * Invokable Monolog processor (callable); does not implement ProcessorInterface to avoid
 * Strauss-broken phpstan Record imports on CI.
 *
 * @package AmeliaBooking\Infrastructure\Services\Logger
 */
class LogSanitizer
{
    private const REDACT_KEYS = [
        'password',
        'pwd',
        'secret',
        'token',
        'accesstoken',
        'refreshtoken',
        'apikey',
        'apikeyid',
        'bearer',
        'authorization',
        'clientsecret',
        'privatekey',
        'cvv',
        'cvc',
        'cardnumber',
        'pan',
        'iban',
        'ssn',
        'webhooksecret',
        'signature',
        'clientid',
        'phone',
        'phonenumber',
        'mobile',
        'telephone',
        'msisdn',
        'to',
        'customer',
        'customername',
        'firstname',
        'lastname',
        'fullname',
        'address',
        'street',
        'postalcode',
        'zip',
        'zipcode',
        'booking',
        'bookings',
        'payment',
        'payments',
        'creditcard',
        'cardholder',
        'cardholdername',
    ];

    /**
     * @param array $record
     *
     * @return array
     */
    public function __invoke(array $record): array
    {
        if (isset($record['message']) && is_string($record['message'])) {
            $record['message'] = $this->sanitizeString($record['message']);
        }

        $record['context'] = $this->sanitize($record['context'] ?? []);
        $record['extra']   = $this->sanitize($record['extra'] ?? []);

        return $record;
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private function sanitize($value)
    {
        if ($value instanceof \Throwable) {
            return $this->sanitizeThrowable($value);
        }

        if (is_object($value)) {
            if ($value instanceof \JsonSerializable) {
                return $this->sanitize($value->jsonSerialize());
            }

            if ($value instanceof \Traversable) {
                return $this->sanitize(iterator_to_array($value));
            }

            return $this->sanitize((array) $value);
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                if (is_string($key) && $this->isSensitiveKey($key)) {
                    $sanitized[$key] = '***REDACTED***';
                    continue;
                }

                $sanitized[$key] = $this->sanitize($item);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return $this->sanitizeString($value);
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $key));

        return in_array($normalized, self::REDACT_KEYS, true);
    }

    private function sanitizeString(string $value): string
    {
        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $encoded = json_encode(
                $this->sanitize($decoded),
                JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );

            if (is_string($encoded)) {
                return $encoded;
            }
        }

        $value = preg_replace(
            '/(password|pwd|secret|token|api[_-]?key|bearer|authorization)\s*[:=]\s*[^&\s"\']+/i',
            '$1=***REDACTED***',
            $value
        );
        $value = preg_replace(
            '/"(password|pwd|secret|token|api[_-]?key|bearer|authorization|'
            . 'firstName|lastName|email|phone|customer|booking|payment|address|'
            . 'cardNumber|pan|creditCard|cardHolder)"\s*:\s*"[^"]*"/i',
            '"$1":"***REDACTED***"',
            $value
        );

        $value = preg_replace_callback(
            '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
            function (array $matches): string {
                return $this->maskEmail($matches[0]);
            },
            $value
        );

        return preg_replace_callback(
            '/(?<!\w)(?:\+|00)?\d[\d\s().\-]{5,18}\d(?!\d)/',
            function (array $matches): string {
                if (
                    $this->isValidCalendarDate($matches[0])
                    || filter_var($matches[0], FILTER_VALIDATE_IP) !== false
                ) {
                    return $matches[0];
                }

                return $this->maskPhone($matches[0]);
            },
            $value
        );
    }

    private function isValidCalendarDate(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $masked = mb_substr($local, 0, 1) . str_repeat('*', max(mb_strlen($local) - 1, 1));

        return $masked . '@' . $domain;
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === null || strlen($digits) < 7) {
            return '***REDACTED***';
        }

        return '***' . substr($digits, -4);
    }

    private function sanitizeThrowable(\Throwable $exception): array
    {
        $data = [
            'class'   => get_class($exception),
            'message' => $this->sanitizeString($exception->getMessage()),
            'code'    => $exception->getCode(),
            'file'    => $this->relativizePath($exception->getFile()),
            'line'    => $exception->getLine(),
        ];

        $caller = $this->findAmeliaCaller($exception);

        if ($caller !== null) {
            $data['caller'] = $caller;
        }

        return $data;
    }

    /**
     * Resolve the Amelia service/handler method that led to the failure (not a full trace).
     */
    private function findAmeliaCaller(\Throwable $exception): ?string
    {
        $pluginRoot = defined('AMELIA_PATH') && is_string(AMELIA_PATH) && AMELIA_PATH !== ''
            ? rtrim(str_replace('\\', '/', AMELIA_PATH), '/') . '/'
            : null;

        $trace = $exception->getTrace();

        if ($pluginRoot !== null) {
            foreach ($trace as $index => $frame) {
                $file = isset($frame['file']) && is_string($frame['file'])
                    ? str_replace('\\', '/', $frame['file'])
                    : null;

                if ($file === null || strpos($file, $pluginRoot) !== 0) {
                    continue;
                }

                if ($this->isLoggingGluePath($file)) {
                    continue;
                }

                // Frame $index is an Amelia call site; the Amelia method that made the call is usually the next frame.
                $next = $trace[$index + 1] ?? null;

                if (
                    is_array($next)
                    && isset($next['class'])
                    && is_string($next['class'])
                    && strpos($next['class'], 'AmeliaBooking\\') === 0
                    && !$this->isLoggingGlueClass($next['class'])
                ) {
                    return $this->formatCaller($next['class'], $next['function'] ?? null);
                }

                // Fallback: failing Amelia method on this frame (e.g. repository method name).
                if (
                    isset($frame['class'])
                    && is_string($frame['class'])
                    && strpos($frame['class'], 'AmeliaBooking\\') === 0
                ) {
                    return $this->formatCaller($frame['class'], $frame['function'] ?? null);
                }

                return $this->relativizePath($file) . (isset($frame['line']) ? ':' . $frame['line'] : '');
            }
        }

        // Throw site invoked via vendor (e.g. Tactician → handler): use first Amelia class on the stack.
        foreach ($trace as $frame) {
            if (
                !isset($frame['class'])
                || !is_string($frame['class'])
                || strpos($frame['class'], 'AmeliaBooking\\') !== 0
                || $this->isLoggingGlueClass($frame['class'])
            ) {
                continue;
            }

            return $this->formatCaller($frame['class'], $frame['function'] ?? null);
        }

        return null;
    }

    private function formatCaller(string $class, ?string $function): string
    {
        $shortClass = strrpos($class, '\\') !== false
            ? substr($class, strrpos($class, '\\') + 1)
            : $class;

        if ($function === null || $function === '') {
            return $shortClass;
        }

        return $shortClass . '::' . $function;
    }

    private function isLoggingGluePath(string $normalizedPath): bool
    {
        return strpos($normalizedPath, '/Infrastructure/Services/Logger/') !== false
            || strpos($normalizedPath, '/Infrastructure/CommandBus/LoggingMiddleware.php') !== false;
    }

    private function isLoggingGlueClass(string $class): bool
    {
        return strpos($class, 'AmeliaBooking\\Infrastructure\\Services\\Logger\\') === 0
            || $class === 'AmeliaBooking\\Infrastructure\\CommandBus\\LoggingMiddleware';
    }

    /**
     * Prefer plugin-relative paths (src/...), then WP-root-relative, else basename.
     * Avoids leaking absolute server paths like /var/www/html/...
     */
    private function relativizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        $pluginRoot = defined('AMELIA_PATH') && is_string(AMELIA_PATH) && AMELIA_PATH !== ''
            ? rtrim(str_replace('\\', '/', AMELIA_PATH), '/') . '/'
            : null;

        if ($pluginRoot !== null && strpos($normalized, $pluginRoot) === 0) {
            return substr($normalized, strlen($pluginRoot));
        }

        if (defined('ABSPATH') && is_string(ABSPATH) && ABSPATH !== '') {
            $wpRoot = rtrim(str_replace('\\', '/', ABSPATH), '/') . '/';

            if (strpos($normalized, $wpRoot) === 0) {
                return substr($normalized, strlen($wpRoot));
            }
        }

        return basename($normalized);
    }
}
