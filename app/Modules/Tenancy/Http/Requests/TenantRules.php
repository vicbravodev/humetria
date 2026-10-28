<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Rule;
use Stringable;

/**
 * @phpstan-type FieldRules list<ValidationRule|Stringable|string|Closure(string, mixed, Closure(string, string|null=): PotentiallyTranslatedString): void>
 */
final class TenantRules
{
    /**
     * The first segment of every route under /admin, plus the paths the app already answers at the root:
     * a company's address must never shadow one of them.
     */
    public const array RESERVED_SLUGS = [
        'admin',
        'api',
        'billing',
        'companies',
        'demo-requests',
        'email',
        'forgot-password',
        'invitation',
        'invitations',
        'login',
        'logout',
        'passkeys',
        'password',
        'register',
        'reset-password',
        'settings',
        'superadmin',
        'two-factor-challenge',
        'user',
    ];

    /**
     * Valid UTF-8 without control characters: Postgres rejects invalid UTF-8 (and the
     * exception would carry the submitted values) and pdo_pgsql silently cuts at a NUL.
     */
    private const string SINGLE_LINE = 'regex:/\A[^\x00-\x1F\x7F]*\z/u';

    /**
     * What a person types is not what is stored: the RFC is uppercase without spaces, the address lowercase.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    public static function normalize(array $input): array
    {
        $normalized = $input;

        foreach (['legal_name', 'trade_name', 'rfc', 'slug'] as $field) {
            $value = $input[$field] ?? null;

            if (! is_string($value)) {
                continue;
            }

            $normalized[$field] = match ($field) {
                'rfc' => Str::of($value)->upper()->replaceMatches('/\s+/u', '')->toString(),
                'slug' => Str::of($value)->trim()->lower()->toString(),
                default => trim($value),
            };
        }

        return $normalized;
    }

    /**
     * @param  mixed  $ignore  the company that may keep its own value, as the route binds it
     * @return FieldRules
     */
    public static function rfc(mixed $ignore = null): array
    {
        return [
            'required',
            'string',
            'regex:/\A[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}\z/u',
            // The date is YYMMDD and the century is unknown. 2000 was a leap year and 1900 was not, so a date
            // that exists in some 19YY or 20YY always exists in 20YY: that one check covers both centuries.
            function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && preg_match('/\A[A-ZÑ&]{3,4}([0-9]{2})([0-9]{2})([0-9]{2})/u', $value, $date) === 1) {
                    [, $year, $month, $day] = array_map(intval(...), $date);

                    if (! checkdate($month, $day, $year + 2000)) {
                        $fail('El RFC tiene una fecha que no existe.');
                    }
                }
            },
            Rule::unique('tenants', 'rfc')->ignore($ignore),
        ];
    }

    /**
     * @param  mixed  $ignore  the company that may keep its own value, as the route binds it
     * @return FieldRules
     */
    public static function slug(mixed $ignore = null): array
    {
        return [
            'required',
            'string',
            'between:3,50',
            'regex:/\A[a-z0-9]+(-[a-z0-9]+)*\z/',
            Rule::notIn(self::RESERVED_SLUGS),
            Rule::unique('tenants', 'slug')->ignore($ignore),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function names(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:200', self::SINGLE_LINE],
            'trade_name' => ['required', 'string', 'max:120', self::SINGLE_LINE],
        ];
    }
}
