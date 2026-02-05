<?php

declare(strict_types=1);

namespace Osticket\Mail\Parser;

use InvalidArgumentException;

/**
 * Structured inbound message parsed from raw RFC/MIME content.
 */
final class ParsedMessage
{
    /** @var array<string, list<string>> */
    private array $headers;

    private ?string $subject;
    private ?string $textBody;
    private ?string $htmlBody;

    /** @var list<ParsedAttachment> */
    private array $attachments;

    /** @var array<string, mixed> */
    private array $meta;

    /**
     * Create a parsed inbound message result.
     *
     * @param array<string, string|list<string>> $headers Parsed message headers.
     * @param string|null $subject Parsed subject when available.
     * @param string|null $textBody Parsed plain text body when available.
     * @param string|null $htmlBody Parsed HTML body when available.
     * @param list<ParsedAttachment> $attachments Parsed inbound attachments.
     * @param array<string, mixed> $meta Additional parser metadata.
     * @return void
     *
     * @throws InvalidArgumentException When headers are invalid.
     */
    public function __construct(
        array $headers = [],
        ?string $subject = null,
        ?string $textBody = null,
        ?string $htmlBody = null,
        array $attachments = [],
        array $meta = []
    ) {
        $this->headers = $this->normalizeHeaders($headers);
        $this->subject = $this->normalizeOptionalBodyValue($subject);
        $this->textBody = $this->normalizeOptionalBodyValue($textBody);
        $this->htmlBody = $this->normalizeOptionalBodyValue($htmlBody);
        $this->attachments = $this->normalizeAttachments($attachments);
        $this->meta = $meta;
    }

    /**
     * Returns normalized headers keyed by lowercase header name.
     *
     * @return array<string, list<string>>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Returns all values for a header name.
     *
     * @param string $name Header name.
     * @return list<string>
     *
     * @throws InvalidArgumentException When the header name is invalid.
     */
    public function header(string $name): array
    {
        $name = $this->normalizeHeaderName($name);

        return $this->headers[$name] ?? [];
    }

    /**
     * Returns the first value for a header name.
     *
     * @param string $name Header name.
     * @return string|null
     *
     * @throws InvalidArgumentException When the header name is invalid.
     */
    public function firstHeader(string $name): ?string
    {
        $values = $this->header($name);

        return $values[0] ?? null;
    }

    /**
     * Returns the parsed subject when available.
     *
     * @return string|null
     */
    public function subject(): ?string
    {
        return $this->subject;
    }

    /**
     * Returns the parsed plain text body when available.
     *
     * @return string|null
     */
    public function textBody(): ?string
    {
        return $this->textBody;
    }

    /**
     * Returns the parsed HTML body when available.
     *
     * @return string|null
     */
    public function htmlBody(): ?string
    {
        return $this->htmlBody;
    }

    /**
     * Returns parser metadata.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    /**
     * Indicates whether a plain text body is available.
     *
     * @return bool
     */
    public function hasTextBody(): bool
    {
        return $this->textBody !== null;
    }

    /**
     * Indicates whether an HTML body is available.
     *
     * @return bool
     */
    public function hasHtmlBody(): bool
    {
        return $this->htmlBody !== null;
    }

    /**
     * Returns parsed inbound attachments and inline MIME parts.
     *
     * @return list<ParsedAttachment>
     */
    public function attachments(): array
    {
        return $this->attachments;
    }

    /**
     * Indicates whether parsed attachments are available.
     *
     * @return bool
     */
    public function hasAttachments(): bool
    {
        return $this->attachments !== [];
    }

    /**
     * Normalize parsed headers into a case-insensitive lookup map.
     *
     * @param array<string, string|list<string>> $headers Parsed message headers.
     * @return array<string, list<string>>
     *
     * @throws InvalidArgumentException When any header is invalid.
     */
    private function normalizeHeaders(array $headers): array
    {
        /** @var array<string, list<string>> $normalized */
        $normalized = [];

        foreach ($headers as $name => $values) {
            if (!\is_string($name)) {
                throw new InvalidArgumentException('Parsed message header names must be strings.');
            }

            $name = $this->normalizeHeaderName($name);
            $values = \is_array($values) ? $values : [$values];

            foreach ($values as $value) {
                if (!\is_string($value)) {
                    throw new InvalidArgumentException('Parsed message header values must be strings.');
                }

                $normalized[$name][] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Normalize parsed attachments.
     *
     * @param list<ParsedAttachment> $attachments Parsed inbound attachments.
     * @return list<ParsedAttachment>
     *
     * @throws InvalidArgumentException When any attachment is invalid.
     */
    private function normalizeAttachments(array $attachments): array
    {
        foreach ($attachments as $attachment) {
            if (!$attachment instanceof ParsedAttachment) {
                throw new InvalidArgumentException('Parsed message attachments must be ParsedAttachment instances.');
            }
        }

        return \array_values($attachments);
    }

    /**
     * Normalize a header name for lookup.
     *
     * @param string $name Header name.
     * @return string
     *
     * @throws InvalidArgumentException When the header name is invalid.
     */
    private function normalizeHeaderName(string $name): string
    {
        $name = \trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Parsed message header name cannot be empty.');
        }

        return \strtolower($name);
    }

    /**
     * Normalize optional body values.
     *
     * @param string|null $value Body value.
     * @return string|null
     */
    private function normalizeOptionalBodyValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value === '' ? null : $value;
    }
}