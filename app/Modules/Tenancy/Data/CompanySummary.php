<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

use App\Modules\Tenancy\Enums\TenantType;

/**
 * A company as other modules show it. A blocked company (deactivated, or whose trial or term ran out) may not be used.
 * Its logo goes on the emails its people get; without one, they show its name.
 */
final readonly class CompanySummary
{
    /**
     * @param  string  $slug  Its address under /admin/, for links into it.
     */
    public function __construct(public string $id, public string $tradeName, public string $slug, public bool $blocked, public ?CompanyLogo $logo = null, public TenantType $type = TenantType::Company) {}

    /** "Autopartes Demo" → "AD": the first letters of its first two words, where its emails have no logo. */
    public function initials(): string
    {
        return self::initialsOf($this->tradeName);
    }

    public static function initialsOf(string $name): string
    {
        $words = array_slice(preg_split('/\s+/', trim($name)) ?: [], 0, 2);

        return mb_strtoupper(implode('', array_map(fn (string $word): string => mb_substr($word, 0, 1), $words)));
    }

    public function isUnion(): bool
    {
        return $this->type === TenantType::Union;
    }
}
