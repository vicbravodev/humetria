<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Enums;

/** Where the AI summary of a group of comments stands. Only `Ready` has themes; there is never a summary made of the original text. */
enum SummaryStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Withheld = 'withheld';
    case AiOff = 'ai_off';
    case Failed = 'failed';
}
