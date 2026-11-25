<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/**
 * What makes the pages of a company its own (white label): its name, its initials where it has no logo, its logo (and the one for dark mode) and the
 * css of its accent (null while it keeps Humetría's). Humetría stays only as the "hecho con humetría" seal.
 */
final readonly class CompanyBrand
{
    public function __construct(public string $name, public string $initials, public ?CompanyLogo $logo, public ?CompanyLogo $darkLogo, public ?string $css) {}

    public static function of(CompanySummary $company): self
    {
        return new self($company->tradeName, $company->initials(), $company->logo, $company->darkLogo, $company->brandColor === null ? null : BrandPalette::from($company->brandColor)->css());
    }
}
