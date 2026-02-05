<?php

declare(strict_types=1);

namespace Osticket\Mail\Auth\Credentials;

/**
 * Username/password credentials for transport authentication.
 */
final class PasswordCredentials implements AuthCredentials
{
    /**
     * Creates password-based authentication credentials.
     *
     * @param string $username Authentication username.
     * @param string $password Authentication password.
     * @return void
     */
    public function __construct(
        private string $username,
        private string $password
    ) {}

    /**
     * Returns the authentication username.
     *
     * @return string
     */
    public function username(): string
    {
        return $this->username;
    }

    /**
     * Returns the authentication password.
     *
     * @return string
     */
    public function password(): string
    {
        return $this->password;
    }
}
