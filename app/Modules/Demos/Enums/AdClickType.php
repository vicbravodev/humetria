<?php

declare(strict_types=1);

namespace App\Modules\Demos\Enums;

/**
 * The click ids Google Ads appends to the address of an ad with auto-tagging: the gclid and, where it cannot be used (iOS),
 * the gbraid (an app) or the wbraid (the web). In order of preference: a visit that brings more than one keeps the first.
 */
enum AdClickType: string
{
    case Gclid = 'gclid';
    case Gbraid = 'gbraid';
    case Wbraid = 'wbraid';
}
