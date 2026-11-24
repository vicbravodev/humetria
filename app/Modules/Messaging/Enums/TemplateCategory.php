<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Enums;

/** The category of a template, as Meta names it. */
enum TemplateCategory: string
{
    case Utility = 'UTILITY';
    case Authentication = 'AUTHENTICATION';
}
