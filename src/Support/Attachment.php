<?php

declare(strict_types=1);

namespace Osticket\Mail\Support;

use InvalidArgumentException;

/**
 * Immutable value object representing a binary attachment.
 */
final class Attachment
{
    private string $filename;
    private string $mimeType;
    /** @var resource|string */
    private mixed $content;

    /**
     * Create a new attachment.
     *
     * @param string $filename Attachment filename.
     * @param string $mimeType MIME type of the attachment.
     * @param resource|string $content Raw content or readable stream.
     * @throws InvalidArgumentException When inputs are invalid.
     * @return void
     */
    public function __construct(string $filename, string $mimeType, $content)
    {
        $filename = \trim($filename);
        $mimeType = \trim($mimeType);

        if ($filename === '') {
            throw new InvalidArgumentException('Attachment filename cannot be empty.');
        }

        if ($mimeType === '') {
            throw new InvalidArgumentException('Attachment MIME type cannot be empty.');
        }

        if (!\is_string($content) && !\is_resource($content)) {
            throw new InvalidArgumentException('Attachment content must be a string or resource.');
        }

        $this->filename = $filename;
        $this->mimeType = $mimeType;
        $this->content = $content;
    }

    /**
     * Get the attachment filename.
     *
     * @return string
     */
    public function filename(): string
    {
        return $this->filename;
    }

    /**
     * Get the attachment MIME type.
     *
     * @return string
     */
    public function mimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Get the attachment content.
     *
     * @return resource|string
     */
    public function content()
    {
        return $this->content;
    }
}
