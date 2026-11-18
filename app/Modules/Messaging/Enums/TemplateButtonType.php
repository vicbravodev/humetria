<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Enums;

enum TemplateButtonType: string
{
    case Url = 'URL';
    case QuickReply = 'QUICK_REPLY';
    case Phone = 'PHONE_NUMBER';
    case CopyCode = 'COPY_CODE';
}
