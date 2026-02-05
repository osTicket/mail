<?php

declare(strict_types=1);

namespace Osticket\Mail\Support;

use Osticket\Mail\Exceptions\MailException;
use InvalidArgumentException;
use Random\RandomException;
use Throwable;

/**
 * Immutable value object for RFC-compatible Message-ID generation.
 */
final class MessageId
{
    private string $value;

    /**
     * Create a new MessageId from a fully formatted value (including angle brackets).
     *
     * @param string $value A full Message-ID value like "<id@domain>".
     * @return void
     *
     * @throws InvalidArgumentException When the value is not a valid Message-ID shape.
     */
    public function __construct(string $value)
    {
        $value = \trim($value);

        if ($value === '' || $value[0] !== '<' || \substr($value, -1) !== '>') {
            throw new InvalidArgumentException('Message-ID must include surrounding angle brackets.');
        }

        $inner = \substr($value, 1, -1);
        if ($inner === '' || \strpos($inner, '@') === false) {
            throw new InvalidArgumentException('Message-ID must contain an "@domain" portion.');
        }

        // Ensure no whitespace which would break header rendering.
        if (\preg_match('/\s/', $inner) === 1) {
            throw new InvalidArgumentException('Message-ID must not contain whitespace.');
        }

        $this->value = $value;
    }

    /**
     * Generate a new mechanical Message-ID using a domain and entropy source.
     *
     * @param string $domain The domain part (e.g. "example.com").
     * @param callable|null $entropy Optional entropy provider returning raw bytes for deterministic testing.
     * @param int|null $timestamp Optional UNIX timestamp for deterministic testing.
     * @return self
     *
     * @throws InvalidArgumentException When the domain is invalid or the entropy source is insufficient.
     * @throws MailException When secure random bytes cannot be generated or entropy fails.
     */
    public static function generate(string $domain, ?callable $entropy = null, ?int $timestamp = null): self
    {
        $domain = \strtolower(\trim($domain));

        if ($domain === ''
            || !\preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
            throw new InvalidArgumentException('Invalid Message-ID domain.');
        }

        $timestamp = $timestamp ?? \time();

        try {
            $bytes = $entropy !== null
                ? (string) $entropy(16)
                : \random_bytes(16);
        } catch (RandomException $e) {
            throw new MailException('Failed to generate Message-ID.', 0, $e);
        } catch (Throwable $e) {
            throw new MailException('Failed to generate Message-ID.', 0, $e);
        }

        if (\strlen($bytes) < 16) {
            throw new InvalidArgumentException('Entropy provider must return at least 16 bytes.');
        }

        $token = \bin2hex(\substr($bytes, 0, 16));

        // Simple, mechanical uniqueness: timestamp + token + domain.
        $id = \sprintf('<%d.%s@%s>', $timestamp, $token, $domain);

        return new self($id);
    }

    /**
     * Creates a Message-ID from user input, canonicalizing and enforcing RFC shape.
     *
     * @param string $value Raw input (may include "<>", may omit "@domain").
     * @param string $fallbackDomain Domain to append when input has no "@domain".
     * @return self
     *
     * @throws InvalidArgumentException When the value or fallback domain are invalid.
     */
    public static function fromUser(string $value, string $fallbackDomain): self
    {
        $value = \trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Message-ID value cannot be empty.');
        }

        $inner = \trim($value, '<>');

        if ($inner === '') {
            throw new InvalidArgumentException('Message-ID inner value cannot be empty.');
        }

        if (\strpos($inner, '@') === false) {
            $fallbackDomain = \strtolower(\trim($fallbackDomain));

            if ($fallbackDomain === ''
                || !\preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $fallbackDomain)
            ) {
                throw new InvalidArgumentException('Invalid Message-ID fallback domain.');
            }

            $inner = $inner . '@' . $fallbackDomain;
        }

        return new self('<' . $inner . '>');
    }

    /**
     * Renders the Message-ID as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Get the full Message-ID value (including angle brackets).
     *
     * @return string
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Render the Message-ID as a string.
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->value;
    }
}
