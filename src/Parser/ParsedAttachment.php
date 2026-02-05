<?php

declare(strict_types=1);

namespace Osticket\Mail\Parser;

use InvalidArgumentException;

/**
 * Parsed inbound attachment or inline MIME part extracted from a raw message.
 */
final class ParsedAttachment
{
    private ?string $filename;
    private string $contentType;
    private string $content;
    private ?string $contentId;
    private ?string $disposition;

    /** @var array<string, mixed> */
    private array $meta;

    /**
     * Create a parsed inbound attachment result.
     *
     * @param string|null $filename Attachment filename when available.
     * @param string $contentType Attachment MIME type.
     * @param string $content Decoded attachment content.
     * @param string|null $contentId Content-ID without angle brackets when available.
     * @param string|null $disposition Content-Disposition value when available.
     * @param array<string, mixed> $meta Additional parser metadata.
     * @return void
     *
     * @throws InvalidArgumentException When attachment values are invalid.
     */
    public function __construct(
        ?string $filename,
        string $contentType,
        string $content,
        ?string $contentId = null,
        ?string $disposition = null,
        array $meta = []
    ) {
        $contentType = \strtolower(\trim($contentType));

        if ($contentType === '') {
            throw new InvalidArgumentException('Parsed attachment content type cannot be empty.');
        }

        $this->filename = $this->normalizeOptionalString($filename, 'filename');
        $this->contentType = $contentType;
        $this->content = $content;
        $this->contentId = $this->normalizeContentId($contentId);
        $this->disposition = $this->normalizeDisposition($disposition);
        $this->meta = $meta;
    }

    /**
     * Returns the attachment filename when available.
     *
     * @return string|null
     */
    public function filename(): ?string
    {
        return $this->filename;
    }

    /**
     * Returns the attachment MIME type.
     *
     * @return string
     */
    public function contentType(): string
    {
        return $this->contentType;
    }

    /**
     * Returns the decoded attachment content.
     *
     * @return string
     */
    public function content(): string
    {
        return $this->content;
    }

    /**
     * Returns the Content-ID without angle brackets when available.
     *
     * @return string|null
     */
    public function contentId(): ?string
    {
        return $this->contentId;
    }

    /**
     * Returns the normalized content disposition when available.
     *
     * @return string|null
     */
    public function disposition(): ?string
    {
        return $this->disposition;
    }

    /**
     * Indicates whether this attachment is intended for inline CID use.
     *
     * @return bool
     */
    public function isInline(): bool
    {
        return $this->contentId !== null || $this->disposition === 'inline';
    }

    /**
     * Indicates whether this attachment should be treated as a regular attachment.
     *
     * @return bool
     */
    public function isAttachment(): bool
    {
        return !$this->isInline();
    }

    /**
     * Returns the decoded attachment size in bytes.
     *
     * @return int
     */
    public function size(): int
    {
        return \strlen($this->content);
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
     * Normalize optional string values.
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
            throw new InvalidArgumentException(\sprintf('Parsed attachment %s cannot be empty.', $label));
        }

        return $value;
    }

    /**
     * Normalize Content-ID values for cid: URL matching.
     *
     * @param string|null $contentId Candidate Content-ID.
     * @return string|null
     *
     * @throws InvalidArgumentException When the Content-ID is invalid.
     */
    private function normalizeContentId(?string $contentId): ?string
    {
        $contentId = $this->normalizeOptionalString($contentId, 'content id');

        if ($contentId === null) {
            return null;
        }

        $contentId = \trim($contentId, "<> \t\n\r\0\x0B");

        if ($contentId === '') {
            throw new InvalidArgumentException('Parsed attachment content id cannot be empty.');
        }

        return $contentId;
    }

    /**
     * Normalize Content-Disposition values.
     *
     * @param string|null $disposition Candidate disposition.
     * @return string|null
     *
     * @throws InvalidArgumentException When the disposition is invalid.
     */
    private function normalizeDisposition(?string $disposition): ?string
    {
        $disposition = $this->normalizeOptionalString($disposition, 'disposition');

        return $disposition === null ? null : \strtolower($disposition);
    }
}