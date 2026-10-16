<?php
namespace Pika;

/** Password rules shared by the app, the CLI and the installer (no dependencies, so it can load before vendor/). */
class PasswordPolicy
{
    /** Problems with a candidate password (empty array = acceptable). bcrypt only uses the first 72 bytes. */
    public static function errors(string $password, string $username = ''): array
    {
        $errors = [];
        if (mb_strlen($password) < 10)  $errors[] = 'Password must be at least 10 characters.';
        if (strlen($password) > 72)     $errors[] = 'Password may not be longer than 72 bytes.';
        if (count(array_unique(mb_str_split($password))) < 4) $errors[] = 'Password is too repetitive.';
        if ($username !== '' && stripos($password, $username) !== false) $errors[] = 'Password may not contain the username.';
        return $errors;
    }
}
