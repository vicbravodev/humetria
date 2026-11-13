<?php

declare(strict_types=1);

namespace App\Support;

/** Whom a person can write to at Humetría, as a sentence to end a message with; nothing while no support address is set. */
final class SupportContact
{
    /** " Escríbenos a ayuda@…." with its leading space, or an empty string. */
    public static function sentence(): string
    {
        $email = config('app.support_email');

        return is_string($email) && $email !== '' ? " Escríbenos a {$email}." : '';
    }
}
