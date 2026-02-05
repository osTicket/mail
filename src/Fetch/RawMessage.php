<?php

declare(strict_types=1);

namespace Osticket\Mail\Fetch;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Raw inbound RFC/MIME message plus fetch/provider metadata.
 */
final class RawMessage
{
    private string $content;
    private ?string $providerId;
    private ?string $mailbox;
    private ?DateTimeImmutable $receivedAt;
    private int $size;

    /** @var string[] */
    private array $flags;

    /** @var array<string, mixed> */
    private array $meta;

    /**
     * Create a raw inbound message value object.
     *
     * @param string $content Raw RFC/MIME message content.
     * @param string|null $providerId Provider/backend message identifier when available.
     * @param string|null $mailbox Mailbox or folder name when available.
     * @param DateTimeImmutable|null $receivedAt Provider/backend received timestamp when available.
     * @param int|null $size Message size in bytes; defaults to content length.
     * @param string[] $flags Provider/backend flags such as Seen or Answered.
     * @param array<string, mixed> $meta Additional non-policy metadata from the fetch backend.
     * @return void
     *
     * @throws InvalidArgumentException When required values are invalid.
     */
    public function __construct(
        string $content,
        ?string $providerId = null,
        ?string $mailbox = null,
        ?DateTimeImmutable $receivedAt = null,
        ?int $size = null,
        array $flags = [],
        array $meta = []
    ) {
        if (\trim($content) === '') {
            throw new InvalidArgumentException('Raw message content cannot be empty.');
        }

        if ($size !== null && $size < 0) {
            throw new InvalidArgumentException('Raw message size cannot be negative.');
        }

        $this->content = $content;
        $this->providerId = $this->normalizeOptionalString($providerId, 'provider id');
        $this->mailbox = $this->normalizeOptionalString($mailbox, 'mailbox');
        $this->receivedAt = $receivedAt;
        $this->size = $size ?? \strlen($content);
        $this->flags = $this->normalizeFlags($flags);
        $this->meta = $meta;
    }

    /**
     * Create a raw message from a string payload.
     *
     * @param string $content Raw RFC/MIME message content.
     * @param array<string, mixed> $meta Additional non-policy metadata from the fetch backend.
     * @return self
     *
     * @throws InvalidArgumentException When the content is empty.
     */
    public static function fromString(string $content, array $meta = []): self
    {
        return new self($content, meta: $meta);
    }

    /**
     * Returns the raw RFC/MIME message content.
     *
     * @return string
     */
    public function content(): string
    {
        return $this->content;
    }

    /**
     * Returns the provider/backend message identifier when available.
     *
     * @return string|null
     */
    public function providerId(): ?string
    {
        return $this->providerId;
    }

    /**
     * Returns the mailbox or folder name when available.
     *
     * @return string|null
     */
    public function mailbox(): ?string
    {
        return $this->mailbox;
    }

    /**
     * Returns the provider/backend received timestamp when available.
     *
     * @return DateTimeImmutable|null
     */
    public function receivedAt(): ?DateTimeImmutable
    {
        return $this->receivedAt;
    }

    /**
     * Returns the message size in bytes.
     *
     * @return int
     */
    public function size(): int
    {
        return $this->size;
    }

    /**
     * Returns provider/backend flags.
     *
     * @return string[]
     */
    public function flags(): array
    {
        return $this->flags;
    }

    /**
     * Indicates whether the message has the given flag.
     *
     * @param string $flag Provider/backend flag name.
     * @return bool
     */
    public function hasFlag(string $flag): bool
    {
        $flag = \trim($flag);

        if ($flag === '') {
            return false;
        }

        return \in_array($flag, $this->flags, true);
    }

    /**
     * Returns additional fetch metadata.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    /**
     * Normalize optional non-empty string values.
     *
     * @param string|null $value Candidate value.
     * @param string $label Human-readable label for error messages.
     * @return string|null
     *
     * @throws InvalidArgumentException When the value is an empty string.
     */
    private function normalizeOptionalString(?string $value, string $label): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = \trim($value);

        if ($value === '') {
            throw new InvalidArgumentException(\sprintf('Raw message %s cannot be empty.', $label));
        }

        return $value;
    }

    /**
     * Normalize provider/backend flags.
     *
     * @param string[] $flags Candidate flags.
     * @return string[]
     *
     * @throws InvalidArgumentException When any flag is invalid.
     */
    private function normalizeFlags(array $flags): array
    {
        $normalized = [];

        foreach ($flags as $flag) {
            if (!\is_string($flag)) {
                throw new InvalidArgumentException('Raw message flags must be strings.');
            }

            $flag = \trim($flag);

            if ($flag === '') {
                throw new InvalidArgumentException('Raw message flags cannot be empty.');
            }

            $normalized[] = $flag;
        }

        return \array_values(\array_unique($normalized));
    }
}