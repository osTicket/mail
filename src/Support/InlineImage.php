<?php

declare(strict_types=1);

namespace Osticket\Mail\Support;

use InvalidArgumentException;

/**
 * Immutable value object representing an inline image attachment.
 */
final class InlineImage
{
    private string $filename;
    private string $mimeType;
    private string $contentId;
    /** @var resource|string */
    private mixed $content;

    /**
     * Create a new inline image.
     *
     * @param string $filename Image filename.
     * @param string $mimeType MIME type of the image.
     * @param string $contentId Content-ID without angle brackets.
     * @param resource|string $content Raw content or readable stream.
     * @throws InvalidArgumentException When inputs are invalid.
     * @return void
     */
    public function __construct(string $filename, string $mimeType, string $contentId, $content)
    {
        $filename = \trim($filename);
        $mimeType = \trim($mimeType);
        $contentId = \trim($contentId);

        if ($filename === '') {
            throw new InvalidArgumentException('Inline image filename cannot be empty.');
        }

        if ($mimeType === '') {
            throw new InvalidArgumentException('Inline image MIME type cannot be empty.');
        }

        if ($contentId === '' || \preg_match('/\s/', $contentId) === 1) {
            throw new InvalidArgumentException('Inline image content ID must be non-empty and contain no whitespace.');
        }

        if ($contentId[0] === '<' || \substr($contentId, -1) === '>') {
            throw new InvalidArgumentException('Inline image content ID must not include angle brackets.');
        }

        if (\strpos($contentId, '@') === false) {
            throw new InvalidArgumentException('Inline image content ID must contain an "@domain" portion.');
        }

        if (!\is_string($content) && !\is_resource($content)) {
            throw new InvalidArgumentException('Inline image content must be a string or resource.');
        }

        $this->filename = $filename;
        $this->mimeType = $mimeType;
        $this->contentId = $contentId;
        $this->content = $content;
    }

    /**
     * Get the image filename.
     *
     * @return string
     */
    public function filename(): string
    {
        return $this->filename;
    }

    /**
     * Get the image MIME type.
     *
     * @return string
     */
    public function mimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Get the Content-ID (without angle brackets).
     *
     * @return string
     */
    public function contentId(): string
    {
        return $this->contentId;
    }

    /**
     * Get the image content.
     *
     * @return resource|string
     */
    public function content()
    {
        return $this->content;
    }
}
