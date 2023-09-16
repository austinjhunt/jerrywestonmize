<?php
/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Slim\Http;

use InvalidArgumentException;
use AmeliaPsr\Http\Message\MessageInterface;
use AmeliaPsr\Http\Message\StreamInterface;
use Slim\Interfaces\Http\HeadersInterface;

/**
 * Abstract message (base class for Request and Response)
 *
 * This class represents a general HTTP message. It provides common properties and methods for
 * the HTTP request and response, as defined in the PSR-7 MessageInterface.
 *
 * @link https://github.com/php-fig/http-message/blob/master/src/MessageInterface.php
 * @see \Slim\Http\Request
 * @see \Slim\Http\Response
 */
abstract class Message implements MessageInterface
{
    /**
     * @var string
     */
    protected $protocolVersion = '1.1';

    /**
     * @var array
     */
    protected static $validProtocolVersions = [
        '1.0' => true,
        '1.1' => true,
        '2.0' => true,
        '2' => true,
    ];

    /**
     * @var HeadersInterface
     */
    protected $headers;

    /**
     * @var StreamInterface
     */
    protected $body;

    /**
     * Disable magic setter to ensure immutability
     */
    public function __set($name, $value)
    {
        // Do nothing
    }

    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string
     */
    public function getProtocolVersion()
    {
        return $this->protocolVersion;
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     *
     * The version string MUST contain only the HTTP version number (e.g.,
     * "1.1", "1.0").
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new protocol version.
     *
     * @param string $version HTTP protocol version
     *
     * @return static
     *
     * @throws InvalidArgumentException if the http version is an invalid number
     */
    public function withProtocolVersion($version)
    {
        if (!isset(self::$validProtocolVersions[$version])) {
            throw new InvalidArgumentException(
                'Invalid HTTP version. Must be one of: '
                . implode(', ', array_keys(self::$validProtocolVersions))
            );
        }
        $clone = clone $this;
        $clone->protocolVersion = $version;

        return $clone;
    }

    /**
     * Retrieves all message header values.
     *
     * Returns an associative array of the message's headers.
     * Each key MUST be a header name, and each value MUST be an array of strings for that header.
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *
     *     // Represent the headers as a string
     *     foreach ($message->getHeaders() as $name => $values) {
     *         echo $name . ": " . implode(", ", $values);
     *     }
     *
     *     // Emit headers iteratively:
     *     foreach ($message->getHeaders() as $name => $values) {
     *         foreach ($values as $value) {
     *             header(sprintf('%s: %s', $name, $value), false);
     *         }
     *     }
     *
     * While header names are not case-sensitive, getHeaders() will preserve the
     * exact case in which headers were originally specified.
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers->all();
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * Returns true if any header names match the given header name using a case-insensitive string comparison.
     * Returns false if no matching header name is found in the message.
     *
     * @param string $name Case-insensitive header field name.
     *
     * @return bool
     */
    public function hasHeader($name)
    {
        return $this->headers->has($name);
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     *
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param string $name Case-insensitive header field name.
     *
     * @return string[]
     */
    public function getHeader($name)
    {
        return $this->headers->get($name, []);
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns a string of all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param string $name Case-insensitive header field name.
     *
     * @return string
     */
    public function getHeaderLine($name)
    {
        return implode(',', $this->headers->get($name, []));
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     *
     * While header names are case-insensitive, the casing of the header will
     * be preserved by this function, and returned from getHeaders().
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new and/or updated header and value.
     *
     * @param string $name Case-insensitive header field name.
     * @param string|string[] $value Header value(s).
     *
     * @return static
     */
    public function withHeader($name, $value)
    {
        $clone = clone $this;
        $clone->headers->set($name, $value);

        return $clone;
    }

    /**
     * Return an instance with the specified header appended with the given value.
     *
     * Existing values for the specified header will be maintained. The new
     * value(s) will be appended to the existing list. If the header did not
     * exist previously, it will be added.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new header and/or value.
     *
     * @param string $name Case-insensitive header field name to add.
     * @param string|string[] $value Header value(s).
     *
     * @return static
     */
    public function withAddedHeader($name, $value)
    {
        $clone = clone $this;
        $clone->headers->add($name, $value);

        if ($this instanceof Response && $this->body instanceof NonBufferedBody) {
            header(sprintf('%s: %s', $name, $clone->getHeaderLine($name)));
        }

        return $clone;
    }

    /**
     * Return an instance without the specified header.
     *
     * Header resolution MUST be done without case-sensitivity.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that removes
     * the named header.
     *
     * @param string $name Case-insensitive header field name to remove.
     *
     * @return static
     */
    public function withoutHeader($name)
    {
        $clone = clone $this;
        $clone->headers->remove($name);

        if ($this instanceof Response && $this->body instanceof NonBufferedBody) {
            header_remove($name);
        }

        return $clone;
    }

    /**
     * Gets the body of the message.
     *
     * @return StreamInterface Returns the body as a stream.
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Return an instance with the specified message body.
     *
     * The body MUST be a StreamInterface object.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return a new instance that has the
     * new body stream.
     *
     * @param StreamInterface $body Body.
     *
     * @return static
     */
    public function withBody(StreamInterface $body)
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }
}
