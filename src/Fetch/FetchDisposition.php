<?php

declare(strict_types=1);

namespace Osticket\Mail\Fetch;

use InvalidArgumentException;

/**
 * Describes an explicit mailbox operation applied after successful processing.
 */
final class FetchDisposition
{
    public const MARK_READ = 'mark_read';
    public const MOVE = 'move';
    public const DELETE = 'delete';

    private string $action;
    private ?string $mailbox;

    /**
     * Create a fetch disposition value object.
     *
     * @param string $action Disposition action.
     * @param string|null $mailbox Target mailbox for move actions.
     * @return void
     *
     * @throws InvalidArgumentException When the action or mailbox is invalid.
     */
    private function __construct(string $action, ?string $mailbox = null)
    {
        $this->action = $this->normalizeAction($action);
        $this->mailbox = $this->normalizeMailbox($mailbox);

        if ($this->action === self::MOVE && $this->mailbox === null) {
            throw new InvalidArgumentException('Move disposition requires a target mailbox.');
        }

        if ($this->action !== self::MOVE && $this->mailbox !== null) {
            throw new InvalidArgumentException('Only move disposition may define a target mailbox.');
        }
    }

    /**
     * Leave the message in the source mailbox and mark it as read.
     *
     * @return self
     */
    public static function markRead(): self
    {
        return new self(self::MARK_READ);
    }

    /**
     * Mark the message as read and move it to another mailbox.
     *
     * @param string $mailbox Target mailbox or folder.
     * @return self
     *
     * @throws InvalidArgumentException When the mailbox is invalid.
     */
    public static function moveTo(string $mailbox): self
    {
        return new self(self::MOVE, $mailbox);
    }

    /**
     * Delete and expunge the message through the IMAP backend.
     *
     * @return self
     */
    public static function delete(): self
    {
        return new self(self::DELETE);
    }

    /**
     * Returns the disposition action.
     *
     * @return string
     */
    public function action(): string
    {
        return $this->action;
    }

    /**
     * Returns the target mailbox for move actions.
     *
     * @return string|null
     */
    public function mailbox(): ?string
    {
        return $this->mailbox;
    }

    /**
     * Returns true when the message should be marked read and left in place.
     *
     * @return bool
     */
    public function shouldMarkRead(): bool
    {
        return $this->action === self::MARK_READ;
    }

    /**
     * Returns true when the message should be moved to another mailbox.
     *
     * @return bool
     */
    public function shouldMove(): bool
    {
        return $this->action === self::MOVE;
    }

    /**
     * Returns true when the message should be deleted and expunged.
     *
     * @return bool
     */
    public function shouldDelete(): bool
    {
        return $this->action === self::DELETE;
    }

    /**
     * Returns true when successful disposition removes the message from the source mailbox.
     *
     * @return bool
     */
    public function isDestructive(): bool
    {
        return $this->shouldMove() || $this->shouldDelete();
    }

    /**
     * Returns the recommended mailbox window for this disposition.
     *
     * @return FetchWindow
     */
    public function fetchWindow(): FetchWindow
    {
        return $this->isDestructive()
            ? FetchWindow::OLDEST
            : FetchWindow::LATEST;
    }

    /**
     * Normalize the disposition action.
     *
     * @param string $action Candidate action.
     * @return string
     *
     * @throws InvalidArgumentException When the action is invalid.
     */
    private function normalizeAction(string $action): string
    {
        $action = \strtolower(\trim($action));

        if (!\in_array($action, [self::MARK_READ, self::MOVE, self::DELETE], true)) {
            throw new InvalidArgumentException('Fetch disposition action is invalid.');
        }

        return $action;
    }

    /**
     * Normalize a target mailbox name.
     *
     * @param string|null $mailbox Candidate mailbox.
     * @return string|null
     *
     * @throws InvalidArgumentException When the mailbox is invalid.
     */
    private function normalizeMailbox(?string $mailbox): ?string
    {
        if ($mailbox === null) {
            return null;
        }

        $mailbox = \trim($mailbox);

        if ($mailbox === '') {
            throw new InvalidArgumentException('Fetch disposition mailbox cannot be empty.');
        }

        return $mailbox;
    }
}