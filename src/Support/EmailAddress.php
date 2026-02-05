<?php

declare(strict_types=1);

namespace Osticket\Mail\Support;

use InvalidArgumentException;

/**
 * Immutable value object representing a single RFC-compliant email address.
 */
final class EmailAddress
{
    private string $email;
    private ?string $name;

    /**
     * Create a new email address value object.
     *
     * @param string $email The email address (addr-spec).
     * @param string|null $name Optional display name.
     * @return void
     *
     * @throws InvalidArgumentException When the email address is invalid.
     */
    public function __construct(string $email, ?string $name = null)
    {
        $email = \trim($email);

        if ($email === '' || !\filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address.');
        }

        $this->email = $email;
        $this->name = $name !== null && \trim($name) !== '' ? \trim($name) : null;
    }

    /**
     * Get the raw email address.
     *
     * @return string
     */
    public function email(): string
    {
        return $this->email;
    }

    /**
     * Get the display name, if present.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return $this->name;
    }

    /**
     * Get the domain portion of the email address.
     *
     * @return string
     */
    public function domain(): string
    {
        $pos = \strrpos($this->email, '@');

        return $pos === false
            ? 'localhost.localdomain'
            : \substr($this->email, $pos + 1);
    }

    /**
     * Render the address in RFC 5322-compatible format.
     *
     * @return string
     */
    public function toString(): string
    {
        if ($this->name === null) {
            return $this->email;
        }

        $escaped = \addcslashes($this->name, '"\\');

        return \sprintf('"%s" <%s>', $escaped, $this->email);
    }
}
