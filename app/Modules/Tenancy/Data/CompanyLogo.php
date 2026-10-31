<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/**
 * A company's logo as an email shows it: its public address and the size in px to show it at (the image is twice that).
 */
final readonly class CompanyLogo
{
    public function __construct(public string $url, public int $width, public int $height) {}
}
