<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

/** What signed a mark made without a connection: the person's passkey, or the key of the device (plan B). */
enum SignerType: string
{
    case Passkey = 'passkey';
    case DeviceKey = 'device_key';
}
