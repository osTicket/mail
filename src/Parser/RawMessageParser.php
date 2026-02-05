<?php

declare(strict_types=1);

namespace Osticket\Mail\Parser;

use Osticket\Mail\Exceptions\ParseException;
use Osticket\Mail\Fetch\RawMessage;
use Throwable;
use ZBateson\MailMimeParser\Header\IHeader;
use ZBateson\MailMimeParser\IMessage;
use ZBateson\MailMimeParser\Message as ZBatesonMessage;
use ZBateson\MailMimeParser\Message\IMessagePart;

/**
 * Parses raw RFC/MIME message content into a structured ParsedMessage result.
 */
final class RawMessageParser
{
    /**
     * Parse a raw RFC/MIME message into a structured result.
     *
     * @param RawMessage|string $message Raw message object or raw RFC/MIME string.
     * @return ParsedMessage
     *
     * @throws ParseException When the message cannot be parsed.
     */
    public function parse(RawMessage|string $message): ParsedMessage
    {
        $raw = $message instanceof RawMessage ? $message->content() : $message;

        if (\trim($raw) === '') {
            throw new ParseException('Raw message content cannot be empty.');
        }

        try {
            $mime = ZBatesonMessage::from($raw, false);
            $headers = $this->extractHeaders($mime);
            $attachments = $this->extractAttachments($mime);
            $meta = $this->extractMeta($message, $mime, $attachments);

            return new ParsedMessage(
                headers: $headers,
                subject: $mime->getSubject(),
                textBody: $mime->getTextContent(),
                htmlBody: $mime->getHtmlContent(),
                attachments: $attachments,
                meta: $meta
            );
        } catch (ParseException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ParseException('Failed to parse raw message.', previous: $e);
        }
    }

    /**
     * Extract normalized headers from a parsed MIME message.
     *
     * @param IMessage $message Parsed MIME message.
     * @return array<string, list<string>>
     */
    private function extractHeaders(IMessage $message): array
    {
        /** @var array<string, list<string>> $headers */
        $headers = [];

        foreach ($message->getAllHeaders() as $header) {
            if (!$header instanceof IHeader) {
                continue;
            }

            $name = \strtolower(\trim($header->getName()));

            if ($name === '') {
                continue;
            }

            $headers[$name][] = $this->normalizeHeaderValue($this->extractHeaderValue($header));
        }

        if ($headers === []) {
            throw new ParseException('Raw message does not contain headers.');
        }

        return $headers;
    }

    /**
     * Extract parsed attachment parts into package-level attachment objects.
     *
     * @param IMessage $message Parsed MIME message.
     * @return list<ParsedAttachment>
     */
    private function extractAttachments(IMessage $message): array
    {
        /** @var list<ParsedAttachment> $attachments */
        $attachments = [];

        foreach ($message->getAllAttachmentParts() as $index => $part) {
            if (!$part instanceof IMessagePart) {
                continue;
            }

            $attachments[] = new ParsedAttachment(
                filename: $part->getFilename(),
                contentType: $part->getContentType('application/octet-stream'),
                content: $this->readAttachmentContent($part),
                contentId: $part->getContentId(),
                disposition: $part->getContentDisposition(),
                meta: [
                    'index' => $index,
                    'charset' => $part->getCharset(),
                    'transfer_encoding' => $part->getContentTransferEncoding(),
                ]
            );
        }

        return $attachments;
    }

    /**
     * Extract parser metadata.
     *
     * @param RawMessage|string $source Original source.
     * @param IMessage $message Parsed MIME message.
     * @param list<ParsedAttachment> $attachments Parsed attachments.
     * @return array<string, mixed>
     */
    private function extractMeta(RawMessage|string $source, IMessage $message, array $attachments): array
    {
        $meta = [
            'parser' => 'zbateson/mail-mime-parser',
            'attachment_count' => \count($attachments),
        ];

        if ($source instanceof RawMessage) {
            $meta += [
                'provider_id' => $source->providerId(),
                'mailbox' => $source->mailbox(),
                'size' => $source->size(),
                'flags' => $source->flags(),
            ];
        }

        return $meta;
    }

    /**
     * Read decoded binary attachment content from a MIME part.
     *
     * @param IMessagePart $part Parsed MIME attachment part.
     * @return string
     *
     * @throws ParseException When attachment content cannot be read.
     */
    private function readAttachmentContent(IMessagePart $part): string
    {
        $handle = $part->getBinaryContentResourceHandle();

        if (\is_resource($handle)) {
            try {
                $content = \stream_get_contents($handle);
            } finally {
                \fclose($handle);
            }

            if ($content === false) {
                throw new ParseException('Failed to read parsed attachment content.');
            }

            return $content;
        }

        return $part->getContent() ?? '';
    }

    /**
     * Extract the consumer-facing header value.
     *
     * Address headers should preserve the full display form instead of only the
     * parsed mailbox address returned by some parser header implementations.
     *
     * @param IHeader $header Parsed MIME header.
     * @return string
     */
    private function extractHeaderValue(IHeader $header): string
    {
        if (\method_exists($header, 'getRawValue')) {
            return (string) $header->getRawValue();
        }

        return (string) $header->getValue();
    }

    /**
     * Normalize folded header whitespace into a single-line value.
     *
     * @param string $value Raw parsed header value.
     * @return string
     */
    private function normalizeHeaderValue(string $value): string
    {
        $normalized = \preg_replace('/\r\n[ \t]+|\n[ \t]+|\r[ \t]+/', ' ', $value);

        if (!\is_string($normalized)) {
            return \trim($value);
        }

        return \trim($normalized);
    }
}