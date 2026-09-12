<?php

declare(strict_types=1);

namespace Rehla\Identity\Data;

use InvalidArgumentException;

final readonly class RegisterCustomerData
{
    public string $name;

    public string $email;

    public string $password;

    public string $preferredLocale;

    public function __construct(
        string $name,
        string $email,
        string $password,
        string $preferredLocale = 'en',
    ) {
        $name = trim($name);
        $email = strtolower(trim($email));

        if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('Customer name must contain between 3 and 100 characters.');
        }

        if (strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Customer email is invalid.');
        }

        if (strlen($password) < 8
            || preg_match('/[A-Z]/', $password) !== 1
            || preg_match('/[a-z]/', $password) !== 1
            || preg_match('/[0-9]/', $password) !== 1) {
            throw new InvalidArgumentException('Customer password does not meet the required policy.');
        }

        if (! in_array($preferredLocale, ['en', 'ar'], true)) {
            throw new InvalidArgumentException('Customer locale must be en or ar.');
        }

        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->preferredLocale = $preferredLocale;
    }
}
