<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

use App\Modules\Tenancy\Enums\TenantType;

/**
 * A company as other modules show it. A blocked company may not be used: its portals, devices and jobs do not operate. It is
 * blocked when it is suspended or archived, and also read only (a person's decision, or its trial or term ran out): then its
 * people still read and export in the panel, but nothing operates.
 * Its logo goes on the emails its people get; without one, they show its name.
 */
final readonly class CompanySummary
{
    /**
     * @param  string  $slug  Its address under /admin/, for links into it.
     */
    public function __construct(public string $id, public string $tradeName, public string $slug, public bool $blocked, public ?CompanyLogo $logo = null, public TenantType $type = TenantType::Company, public bool $readOnly = false) {}

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
