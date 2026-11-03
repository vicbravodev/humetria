<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Enums;

/** How the person chose to go: without a name, or with it for the handlers only. There is no mode «identified for everyone». */
enum IdentityMode: string
{
    case Anonymous = 'anonymous';
    case Confidential = 'confidential';

    public function label(): string
    {
        return match ($this) {
            self::Anonymous => 'Anónima',
            self::Confidential => 'Confidencial, con nombre',
        };
    }
}
