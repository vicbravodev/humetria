<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\SubscriptionStatus;
use Illuminate\Validation\Rule;

/** What the form of a plan asks of each field, shared by the form that saves it and the preview that prices it. */
final class SubscriptionRules
{
    /**
     * @param  bool  $hasPrice  whether the form carries a negotiated price: a validity belongs to one.
     * @return array<string, list<mixed>>
     */
    public static function rules(bool $hasPrice): array
    {
        return [
            'plan' => ['required', Rule::enum(Plan::class)],
            'profile' => ['required', Rule::enum(Profile::class)],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'seats' => ['required', 'integer', 'min:0', 'max:100000'],
            'centers' => ['required', 'integer', 'min:1', 'max:1000'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'negotiated_price' => ['nullable', 'string', 'regex:/\A\d{1,12}(\.\d{1,2})?\z/', 'required_if:profile,'.Profile::Negotiated->value],
            'negotiated_valid_until' => ['nullable', Rule::prohibitedIf(! $hasPrice), 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
    }

    /**
     * What to validate: an empty price or validity is none.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    public static function normalize(array $input): array
    {
        $normalized = $input;

        foreach (['negotiated_price', 'negotiated_valid_until'] as $field) {
            $value = $input[$field] ?? null;
            $normalized[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        return $normalized;
    }
}
