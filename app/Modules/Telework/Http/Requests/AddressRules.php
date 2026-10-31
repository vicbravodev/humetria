<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Validation\Rule;

/**
 * The rules of an address, the same as a work center's: one place for the aspirant's workplaces and for adding one later.
 */
final class AddressRules
{
    /** Valid UTF-8 without control characters: Postgres rejects the rest. */
    private const string SINGLE_LINE = 'regex:/\A[^\x00-\x1F\x7F]*\z/u';

    /**
     * @param  string  $prefix  Where the fields sit in the input: '' or 'workplaces.*.'.
     * @return array<string, list<mixed>>
     */
    public static function for(string $prefix = ''): array
    {
        return [
            "{$prefix}street" => ['required', 'string', 'max:150', self::SINGLE_LINE],
            "{$prefix}exterior_number" => ['required', 'string', 'max:20', self::SINGLE_LINE],
            "{$prefix}interior_number" => ['nullable', 'string', 'max:20', self::SINGLE_LINE],
            "{$prefix}neighborhood" => ['required', 'string', 'max:120', self::SINGLE_LINE],
            "{$prefix}postal_code" => ['required', 'string', 'regex:/\A[0-9]{5}\z/'],
            "{$prefix}municipality" => ['required', 'string', 'max:120', self::SINGLE_LINE],
            "{$prefix}state" => ['required', 'string', Rule::in(array_keys(resolve(WorkCenters::class)->states()))],
        ];
    }
}
