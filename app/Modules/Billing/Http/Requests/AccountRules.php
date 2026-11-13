<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\AccountKind;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Support\SatCatalogs;
use Illuminate\Validation\Rule;

/**
 * What both forms of a payer account ask of each field, so the console and the database agree.
 */
final class AccountRules
{
    private const string SINGLE_LINE = 'regex:/\A[^\r\n]*\z/u';

    /** The fields that, left empty, are stored as nothing. */
    private const array OPTIONAL = ['tax_regime', 'postal_code', 'cfdi_use', 'contact_phone', 'notes'];

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(?BillingAccount $ignore = null): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:200', self::SINGLE_LINE],
            'rfc' => ['required', 'string', 'regex:/\A[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}\z/u', Rule::unique('billing_accounts', 'rfc')->ignore($ignore)],
            'tax_regime' => ['nullable', 'string', Rule::in(SatCatalogs::codes(SatCatalogs::TAX_REGIMES))],
            'postal_code' => ['nullable', 'string', 'regex:/\A[0-9]{5}\z/'],
            'cfdi_use' => ['nullable', 'string', Rule::in(SatCatalogs::codes(SatCatalogs::CFDI_USES))],
            'contact_name' => ['required', 'string', 'max:120', self::SINGLE_LINE],
            'contact_email' => ['required', 'string', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30', self::SINGLE_LINE],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * What to validate: the RFC without spaces and in capitals, the text trimmed, and an empty optional field as null.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    public static function normalize(array $input): array
    {
        $normalized = [];

        foreach ($input as $field => $value) {
            $text = is_string($value) ? trim($value) : $value;
            $normalized[$field] = $text === '' && in_array($field, self::OPTIONAL, true) ? null : $text;
        }

        if (is_string($normalized['rfc'] ?? null)) {
            $normalized['rfc'] = mb_strtoupper(preg_replace('/\s+/u', '', $normalized['rfc']) ?? '');
        }

        return $normalized;
    }

    /**
     * @return list<mixed>
     */
    public static function kind(): array
    {
        return ['required', Rule::enum(AccountKind::class)];
    }
}
