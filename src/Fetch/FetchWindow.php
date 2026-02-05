<?php

declare(strict_types=1);

namespace Osticket\Mail\Fetch;

/**
 * Selects a bounded window from an IMAP mailbox.
 */
enum FetchWindow: string
{
    case OLDEST = 'oldest';
    case LATEST = 'latest';
}