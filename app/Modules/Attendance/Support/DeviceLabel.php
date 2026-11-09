<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

/**
 * The name of a linked device, written by whoever holds the link and shown to the person in an email: only letters,
 * digits, spaces and . - _ ( ), so it can never carry markup or a link.
 */
final class DeviceLabel
{
    public const string PATTERN = '/^[\pL\pN .\-_()]+$/u';

    /** The label with anything else taken out, for a label that did not come through the request's rule. */
    public static function clean(string $label): string
    {
        return trim((string) preg_replace('/[^\pL\pN .\-_()]+/u', '', $label));
    }
}
