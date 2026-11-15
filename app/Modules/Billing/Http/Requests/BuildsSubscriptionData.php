<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Data\SubscriptionData;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Support\Money;
use Carbon\CarbonImmutable;

/** The rules and the DTO of a plan out of a form: what saves a plan and what prices it read the same fields. */
trait BuildsSubscriptionData
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return SubscriptionRules::rules($this->filled('negotiated_price'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['negotiated_valid_until.prohibited' => 'La vigencia es de un precio negociado: pon primero el precio.'];
    }

    public function toData(): SubscriptionData
    {
        return new SubscriptionData(
            plan: Plan::from($this->string('plan')->toString()),
            profile: Profile::from($this->string('profile')->toString()),
            status: SubscriptionStatus::from($this->string('status')->toString()),
            seats: $this->integer('seats'),
            centers: $this->integer('centers'),
            startsOn: CarbonImmutable::parse($this->string('starts_on')->toString())->startOfDay(),
            negotiatedPriceCents: $this->filled('negotiated_price') ? Money::parse($this->string('negotiated_price')->toString()) : null,
            negotiatedValidUntil: $this->filled('negotiated_valid_until') ? CarbonImmutable::parse($this->string('negotiated_valid_until')->toString())->startOfDay() : null,
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge(SubscriptionRules::normalize($this->all()));
    }
}
