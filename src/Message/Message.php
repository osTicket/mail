<?php

declare(strict_types=1);

namespace Osticket\Mail\Message;

use InvalidArgumentException;
use Osticket\Mail\Exceptions\MailException;
use Osticket\Mail\Support\{
    AddressList,
    Attachment,
    EmailAddress,
    InlineImage,
    MessageId
};
use RuntimeException;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Immutable outbound email message with RFC-compliant structure.
 */
final class Message
{
    private Email $email;

    private ?EmailAddress $from = null;

    private ?EmailAddress $sender = null;

    /** @var EmailAddress[] */
    private array $to = [];

    /** @var EmailAddress[] */
    private array $cc = [];

    /** @var EmailAddress[] */
    private array $bcc = [];

    private bool $finalized = false;

    /** @var Attachment[] */
    private array $attachments = [];

    /** @var InlineImage[] */
    private array $inlineImages = [];

    private ?string $messageId = null;

    private ?string $pendingMessageId = null;

    /**
     * Create a new message with optional seed headers.
     *
     * @param EmailAddress|null $from Sender address.
     * @param AddressList|null $to Recipient list.
     * @param string|null $subject Message subject.
     * @param MessageId|string|null $messageId Optional Message-ID value or token.
     * @return void
     *
     * @throws InvalidArgumentException When subject or Message-ID input is invalid.
     */
    public function __construct(
        ?EmailAddress $from = null,
        ?AddressList $to = null,
        ?string $subject = null,
        MessageId|string|null $messageId = null
    ) {
        $this->email = new Email();

        if ($from !== null) {
            $this->from = $from;
            $this->email->from($from->toString());
        }

        if ($to !== null) {
            $this->to = $to->all();
            $this->email->to(...$this->mapAddresses($to));
        }

        if ($subject !== null) {
            $this->applySubject($subject);
        }

        if ($messageId !== null) {
            $this->assignMessageId($messageId);
        }
    }

    /**
     * Ensure the underlying Symfony Email is cloned for immutability.
     *
     * @return void
     */
    public function __clone()
    {
        $this->email = clone $this->email;
        $this->finalized = false;
    }

    /**
     * Set the From header address.
     *
     * @param EmailAddress $from
     * @return self
     */
    public function withFrom(EmailAddress $from): self
    {
        $clone = clone $this;
        $clone->from = $from;
        $clone->email->from($from->toString());

        return $clone;
    }

    /**
     * Sets the From header address.
     *
     * @param EmailAddress $from
     * @return self
     */
    public function setFrom(EmailAddress $from): self
    {
        return $this->withFrom($from);
    }

    /**
     * Set the Sender header address.
     *
     * @param EmailAddress $sender
     * @return self
     */
    public function withSender(EmailAddress $sender): self
    {
        $clone = clone $this;
        $clone->sender = $sender;
        $clone->email->sender($sender->toString());

        return $clone;
    }

    /**
     * Sets the Sender header address.
     *
     * @param EmailAddress $sender
     * @return self
     */
    public function setSender(EmailAddress $sender): self
    {
        return $this->withSender($sender);
    }

    /**
     * Set an explicit Message-ID value.
     *
     * @param MessageId|string $messageId Message-ID value or token.
     * @return self
     *
     * @throws InvalidArgumentException When Message-ID input is invalid.
     */
    public function withMessageId(MessageId|string $messageId): self
    {
        $clone = clone $this;
        $clone->assignMessageId($messageId);

        return $clone;
    }

    /**
     * Sets an explicit Message-ID value.
     *
     * @param MessageId|string $messageId Message-ID value or token.
     * @return self
     *
     * @throws InvalidArgumentException When Message-ID input is invalid.
     */
    public function setMessageId(MessageId|string $messageId): self
    {
        return $this->withMessageId($messageId);
    }

    /**
     * Set the plain text body.
     *
     * @param string $text
     * @return self
     */
    public function withText(string $text): self
    {
        $clone = clone $this;
        $clone->email->text($text);

        return $clone;
    }

    /**
     * Sets plain text body.
     *
     * @param string $text
     * @return self
     */
    public function setTextBody(string $text): self
    {
        return $this->withText($text);
    }

    /**
     * Set the HTML body.
     *
     * @param string $html
     * @return self
     */
    public function withHtml(string $html): self
    {
        $clone = clone $this;
        $clone->email->html($html);

        return $clone;
    }

    /**
     * Sets HTML body.
     *
     * @param string $html
     * @return self
     */
    public function setHtmlBody(string $html): self
    {
        return $this->withHtml($html);
    }

    /**
     * Replace To recipients.
     *
     * @param AddressList $to
     * @return self
     */
    public function withTo(AddressList $to): self
    {
        $clone = clone $this;
        $clone->to = $to->all();
        $clone->email->to(...$clone->mapAddresses($to));

        return $clone;
    }

    /**
     * Sets To recipients.
     *
     * @param AddressList $to
     * @return self
     */
    public function setTo(AddressList $to): self
    {
        return $this->withTo($to);
    }

    /**
     * Replace CC recipients.
     *
     * @param AddressList $cc
     * @return self
     */
    public function withCc(AddressList $cc): self
    {
        $clone = clone $this;
        $clone->cc = $cc->all();
        $clone->email->cc(...$clone->mapAddresses($cc));

        return $clone;
    }

    /**
     * Adds a "Cc" recipient.
     *
     * @param EmailAddress $cc
     * @return self
     */
    public function addCc(EmailAddress $cc): self
    {
        $clone = clone $this;
        $clone->cc[] = $cc;
        $clone->email->addCc($cc->toString());

        return $clone;
    }

    /**
     * Replace BCC recipients.
     *
     * @param AddressList $bcc
     * @return self
     */
    public function withBcc(AddressList $bcc): self
    {
        $clone = clone $this;
        $clone->bcc = $bcc->all();
        $clone->email->bcc(...$clone->mapAddresses($bcc));

        return $clone;
    }

    /**
     * Adds a "Bcc" recipient.
     *
     * @param EmailAddress $bcc
     * @return self
     */
    public function addBcc(EmailAddress $bcc): self
    {
        $clone = clone $this;
        $clone->bcc[] = $bcc;
        $clone->email->addBcc($bcc->toString());

        return $clone;
    }

    /**
     * Set Reply-To header.
     *
     * @param EmailAddress $replyTo
     * @return self
     */
    public function withReplyTo(EmailAddress $replyTo): self
    {
        $clone = clone $this;
        $clone->email->replyTo($replyTo->toString());

        return $clone;
    }

    /**
     * Sets the Reply-To address.
     *
     * @param EmailAddress $replyTo
     * @return self
     */
    public function setReplyTo(EmailAddress $replyTo): self
    {
        return $this->withReplyTo($replyTo);
    }

    /**
     * Attach a binary attachment.
     *
     * @param Attachment $attachment
     * @return self
     */
    public function withAttachment(Attachment $attachment): self
    {
        $clone = clone $this;
        $clone->attachments[] = $attachment;

        return $clone;
    }

    /**
     * Adds a binary attachment.
     *
     * @param Attachment $attachment
     * @return self
     */
    public function addAttachment(Attachment $attachment): self
    {
        return $this->withAttachment($attachment);
    }

    /**
     * Attach an inline image (CID wiring happens later).
     *
     * @param InlineImage $image
     * @return self
     */
    public function withInlineImage(InlineImage $image): self
    {
        $clone = clone $this;
        $clone->inlineImages[] = $image;

        return $clone;
    }

    /**
     * Adds an inline image.
     *
     * @param InlineImage $inlineImage
     * @return self
     */
    public function addInlineImage(InlineImage $inlineImage): self
    {
        return $this->withInlineImage($inlineImage);
    }

    /**
     * Sets the message subject.
     *
     * @param string $subject
     * @return self
     */
    public function withSubject(string $subject): self
    {
        $clone = clone $this;
        $clone->applySubject($subject);

        return $clone;
    }

    /**
     * Sets the message subject.
     *
     * @param string $subject
     * @return self
     */
    public function setSubject(string $subject): self
    {
        return $this->withSubject($subject);
    }

    /**
     * Apply a validated subject to the underlying message.
     *
     * @param string $subject
     * @return void
     *
     * @throws InvalidArgumentException When subject is empty.
     */
    private function applySubject(string $subject): void
    {
        $subject = \trim($subject);

        if ($subject === '') {
            throw new InvalidArgumentException('Message subject cannot be empty.');
        }

        $this->email->subject($subject);
    }

    /**
     * Adds a "To" recipient.
     *
     * @param EmailAddress $to
     * @return self
     */
    public function addTo(EmailAddress $to): self
    {
        $clone = clone $this;
        $clone->to[] = $to;
        $clone->email->addTo($to->toString());

        return $clone;
    }

    /**
     * Adds a header without replacing existing values.
     *
     * @param string $name Header name.
     * @param string $value Header value.
     * @return self
     */
    public function addHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->email->getHeaders()->addTextHeader($name, $value);

        return $clone;
    }

    /**
     * Sets a header value, replacing any existing values.
     *
     * @param string $name Header name.
     * @param string $value Header value.
     * @return self
     */
    public function setHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $headers = $clone->email->getHeaders();
        $headers->remove($name);
        $headers->addTextHeader($name, $value);

        return $clone;
    }

    /**
     * Removes all instances of a header.
     *
     * @param string $name Header name.
     * @return self
     */
    public function removeHeader(string $name): self
    {
        $clone = clone $this;
        $clone->email->getHeaders()->remove($name);

        return $clone;
    }

    /**
     * Get the From header address.
     *
     * @return EmailAddress|null
     */
    public function from(): ?EmailAddress
    {
        return $this->from;
    }

    /**
     * Get the Sender header address.
     *
     * @return EmailAddress|null
     */
    public function sender(): ?EmailAddress
    {
        return $this->sender;
    }

    /**
     * Get To recipients.
     *
     * @return AddressList|null
     */
    public function to(): ?AddressList
    {
        return $this->addressListOrNull($this->to);
    }

    /**
     * Get CC recipients.
     *
     * @return AddressList|null
     */
    public function cc(): ?AddressList
    {
        return $this->addressListOrNull($this->cc);
    }

    /**
     * Get BCC recipients.
     *
     * @return AddressList|null
     */
    public function bcc(): ?AddressList
    {
        return $this->addressListOrNull($this->bcc);
    }

    /**
     * Get all delivery recipients from To, CC, and BCC headers.
     *
     * @return AddressList
     *
     * @throws InvalidArgumentException When no recipients are set.
     */
    public function allRecipients(): AddressList
    {
        return new AddressList(\array_merge($this->to, $this->cc, $this->bcc));
    }

    /**
     * Get attached binary attachments.
     *
     * @return Attachment[]
     */
    public function attachments(): array
    {
        return $this->attachments;
    }

    /**
     * Get inline images.
     *
     * @return InlineImage[]
     */
    public function inlineImages(): array
    {
        return $this->inlineImages;
    }

    /**
     * Returns the Message-ID header value including angle brackets.
     *
     * @return string
     *
     * @throws MailException
     * @throws InvalidArgumentException When no From address is set.
     */
    public function messageId(): string
    {
        $this->ensureMessageIdAssigned();

        return $this->messageId;
    }

    /**
     * Assigns the Message-ID value or token for this message.
     *
     * @param MessageId|string $messageId
     * @return void
     *
     * @throws InvalidArgumentException When Message-ID input is invalid.
     */
    private function assignMessageId(MessageId|string $messageId): void
    {
        if ($messageId instanceof MessageId) {
            $this->pendingMessageId = null;
            $this->applyMessageId($messageId);

            return;
        }

        $messageId = \trim($messageId);

        if ($messageId === '') {
            throw new InvalidArgumentException('Message-ID value cannot be empty.');
        }

        $this->pendingMessageId = $messageId;
        $this->messageId = null;
        $this->email->getHeaders()->remove('Message-ID');
    }

    /**
     * Ensures the message has a stable Message-ID assigned.
     *
     * @return void
     *
     * @throws MailException
     * @throws InvalidArgumentException When no From address is set.
     * @phpstan-assert !null $this->messageId
     */
    private function ensureMessageIdAssigned(): void
    {
        if ($this->messageId !== null && $this->messageId !== '') {
            return;
        }

        if ($this->from === null) {
            throw new InvalidArgumentException('Message From address must be set before assigning Message-ID.');
        }

        $id = $this->pendingMessageId !== null
            ? MessageId::fromUser($this->pendingMessageId, $this->from->domain())
            : MessageId::generate($this->from->domain());

        $this->applyMessageId($id);
        $this->pendingMessageId = null;
    }

    /**
     * Applies the Message-ID header and caches the canonical value.
     *
     * @param MessageId $id
     * @return void
     */
    private function applyMessageId(MessageId $id): void
    {
        $value = $id->value();
        $inner = \trim($value, '<>');

        $headers = $this->email->getHeaders();
        $headers->remove('Message-ID');
        $headers->addIdHeader('Message-ID', $inner);

        $this->messageId = $value;
    }

    /**
     * Finalize the message before rendering or transport.
     *
     * @return void
     */
    private function finalize(): void
    {
        $this->ensureMessageIdAssigned();
        $this->finalizeFiles();
    }

    /**
     * Applies attachments and inline images to the underlying email.
     *
     * @return void
     */
    private function finalizeFiles(): void
    {
        // Track existing inline/attachments to avoid duplicates
        $existingInline = [];
        $existingAttachments = [];

        foreach ($this->email->getAttachments() as $part) {
            if ($part->getDisposition() === 'inline') {
                $cid = $part->getContentId();
                if (\is_string($cid) && $cid !== '') {
                    $existingInline[$cid] = true;
                }
                continue;
            }

            if ($part->getDisposition() === 'attachment') {
                $filename = $part->getFilename() ?? '';
                $mime = $part->getMediaType() . '/' . $part->getMediaSubtype();
                if ($filename !== '') {
                    $existingAttachments[$filename . '|' . $mime] = true;
                }
            }
        }

        foreach ($this->inlineImages as $image) {
            if (isset($existingInline[$image->contentId()])) {
                continue;
            }

            $part = new DataPart(
                $image->content(),
                $image->filename(),
                $image->mimeType()
            );

            $part->setDisposition('inline');
            $part->setContentId($image->contentId());

            $this->email->addPart($part);
        }

        foreach ($this->attachments as $attachment) {
            $key = $attachment->filename() . '|' . $attachment->mimeType();
            if (isset($existingAttachments[$key])) {
                continue;
            }

            $part = new DataPart(
                $attachment->content(),
                $attachment->filename(),
                $attachment->mimeType()
            );

            $this->email->addPart($part);
        }
    }

    /**
     * Finalizes the message so it is ready for rendering or transport delivery.
     *
     * @return self
     *
     * @throws InvalidArgumentException When required headers are missing.
     */
    public function prepare(): self
    {
        $this->validateForPreparation();
        $this->ensureMessageIdAssigned();

        if (!$this->finalized) {
            $this->finalize();
            $this->finalized = true;
        }

        return $this;
    }

    /**
     * Get the underlying Symfony Email instance.
     *
     * @return Email
     */
    public function email(): Email
    {
        $this->prepare();

        return $this->email;
    }

    /**
     * Render the message as an RFC 5322 / MIME string suitable for transport.
     *
     * @return string
     *
     * @throws RuntimeException When rendering fails.
     */
    public function toString(): string
    {
        $email = $this->email();

        try {
            return $email->toString();
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to render message to RFC string.', 0, $e);
        }
    }

    /**
     * Ensure the message has required headers before preparation.
     *
     * @return void
     *
     * @throws InvalidArgumentException When required headers are missing.
     */
    private function validateForPreparation(): void
    {
        if ($this->from === null) {
            throw new InvalidArgumentException('Message requires a From address for preparation.');
        }

        if ($this->to === [] && $this->cc === [] && $this->bcc === []) {
            throw new InvalidArgumentException('Message requires at least one recipient for preparation.');
        }

        $subject = $this->email->getSubject();
        if ($subject === null || \trim($subject) === '') {
            throw new InvalidArgumentException('Message subject cannot be empty.');
        }
    }

    /**
     * Map AddressList into RFC-ready strings.
     *
     * @param AddressList $list
     * @return string[]
     */
    private function mapAddresses(AddressList $list): array
    {
        return \array_map(
            static fn (EmailAddress $a): string => $a->toString(),
            $list->all()
        );
    }

    /**
     * Convert a non-empty address array into an AddressList.
     *
     * @param EmailAddress[] $addresses
     * @return AddressList|null
     */
    private function addressListOrNull(array $addresses): ?AddressList
    {
        if ($addresses === []) {
            return null;
        }

        return new AddressList($addresses);
    }
}