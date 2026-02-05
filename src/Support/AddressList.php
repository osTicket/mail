<?php

declare(strict_types=1);

namespace Osticket\Mail\Support;

use Countable;
use IteratorAggregate;
use ArrayIterator;
use InvalidArgumentException;

/**
 * Immutable collection of email addresses with deterministic ordering.
 *
 * @implements IteratorAggregate<int, EmailAddress>
 */
final class AddressList implements IteratorAggregate, Countable
{
    /** @var EmailAddress[] */
    private array $addresses;

    /**
     * Create a new address list.
     *
     * @param EmailAddress[] $addresses List of email addresses.
     * @return void
     *
     * @throws InvalidArgumentException When the list is empty.
     */
    public function __construct(array $addresses)
    {
        if ($addresses === []) {
            throw new InvalidArgumentException('Address list cannot be empty.');
        }

        foreach ($addresses as $address) {
            if (!$address instanceof EmailAddress) {
                throw new InvalidArgumentException('Address list must contain only EmailAddress instances.');
            }
        }

        $this->addresses = \array_values($addresses);
    }

    /**
     * Create an address list from a single address.
     *
     * @param EmailAddress $address
     * @return self
     */
    public static function fromOne(EmailAddress $address): self
    {
        return new self([$address]);
    }

    /**
     * Return the addresses as an array.
     *
     * @return EmailAddress[]
     */
    public function all(): array
    {
        return $this->addresses;
    }

    /**
     * Render the list as a comma-separated RFC-compatible string.
     *
     * @return string
     */
    public function toString(): string
    {
        return \implode(
            ', ',
            \array_map(
                static fn (EmailAddress $a): string => $a->toString(),
                $this->addresses
            )
        );
    }

    /**
     * Get an iterator for the addresses.
     *
     * @return ArrayIterator<int, EmailAddress>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->addresses);
    }

    /**
     * Get the number of addresses in the list.
     *
     * @return int
     */
    public function count(): int
    {
        return \count($this->addresses);
    }
}
