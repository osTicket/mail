<?php

declare(strict_types=1);

namespace Osticket\Mail\Exceptions;

/**
 * Package-level exception for reliably identified authentication rejection.
 */
final class AuthenticationException extends ConnectionException {}