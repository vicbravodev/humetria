<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Requests;

use App\Modules\Complaints\Enums\ComplaintCategory;
use App\Modules\Complaints\Enums\ComplaintType;
use App\Modules\Complaints\Enums\IdentityMode;
use App\Modules\Complaints\Enums\RespondentKind;
use App\Modules\Complaints\Support\ComplaintInput;
use App\Modules\Complaints\Support\Dates;
use App\Modules\Complaints\Support\HandlerCoverage;
use App\Modules\Complaints\Support\TicketSigner;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What a person sends from the public form. Anyone with the code may send it: there is nobody to authorize. Errors say
 * what is wrong, never what was written (the validation response carries no input).
 */
final class SubmitComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = ComplaintType::tryFrom($this->string('type')->toString());

        return [
            'ticket' => ['required', 'string', $this->isGoodTicket(...)],
            'type' => ['required', Rule::enum(ComplaintType::class)],
            'category' => ['nullable', Rule::in(array_map(fn (ComplaintCategory $category): string => $category->value, $type === null ? [] : ComplaintCategory::of($type)))],
            'description' => ['required', 'string', 'min:'.config()->integer('complaints.description.min'), 'max:'.config()->integer('complaints.description.max')],
            'occurred_on' => [Rule::requiredIf($type === ComplaintType::TraumaticEventNotice), 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.Dates::today()->toDateString()],
            'work_center_id' => ['nullable', 'bail', 'uuid', $this->isActiveCenter(...)],
            'respondent_kind' => ['required', Rule::enum(RespondentKind::class), $this->someoneIndependentReceives(...)],
            'respondent_text' => ['nullable', 'string', 'max:'.config()->integer('complaints.free_text_max')],
            'identity_mode' => ['required', Rule::in($type === ComplaintType::TraumaticEventNotice ? [IdentityMode::Confidential->value] : array_map(fn (IdentityMode $mode): string => $mode->value, IdentityMode::cases()))],
            'reporter_name' => [Rule::requiredIf($this->string('identity_mode')->toString() === IdentityMode::Confidential->value), 'prohibited_if:identity_mode,anonymous', 'nullable', 'string', 'max:150', 'regex:/\S/u'],
            'reporter_contact' => ['prohibited_if:identity_mode,anonymous', 'nullable', 'string', 'max:200'],
            'notify_email' => ['nullable', 'email:rfc', 'max:190'],
            'involved_text' => ['prohibited_unless:type,traumatic_event_notice', 'nullable', 'string', 'max:'.config()->integer('complaints.free_text_max')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['description' => trim($this->string('description')->toString())]);
    }

    public function complaint(): ComplaintInput
    {
        $category = $this->string('category')->toString();
        $occurredOn = $this->string('occurred_on')->toString();
        $text = $this->trimmedOrNull('respondent_text');

        return new ComplaintInput(
            ComplaintType::from($this->string('type')->toString()),
            $category === '' ? null : ComplaintCategory::from($category),
            $this->string('description')->toString(),
            $occurredOn === '' ? null : CarbonImmutable::parse($occurredOn),
            $this->workCenterId(),
            RespondentKind::from($this->string('respondent_kind')->toString()),
            $text,
            IdentityMode::from($this->string('identity_mode')->toString()),
            $this->trimmedOrNull('reporter_name'),
            $this->trimmedOrNull('reporter_contact'),
            $this->trimmedOrNull('involved_text'),
            $this->trimmedOrNull('notify_email'),
        );
    }

    private function trimmedOrNull(string $key): ?string
    {
        $text = trim($this->string($key)->toString());

        return $text === '' ? null : $text;
    }

    private function workCenterId(): ?string
    {
        return $this->filled('work_center_id') ? $this->string('work_center_id')->toString() : null;
    }

    /** @param  Closure(string): mixed  $fail */
    private function isGoodTicket(string $attribute, mixed $value, Closure $fail): void
    {
        $code = $this->route('code');

        if (! is_string($value) || ! is_string($code) || ! resolve(TicketSigner::class)->isValid($value, $code)) {
            $fail('La página se abrió hace demasiado o demasiado poco. Recargue la página e inténtelo de nuevo.');
        }
    }

    /** @param  Closure(string): mixed  $fail */
    private function isActiveCenter(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || resolve(WorkCenters::class)->findActive($value) === null) {
            $fail('Ese centro de trabajo no está disponible.');
        }
    }

    /** @param  Closure(string): mixed  $fail */
    private function someoneIndependentReceives(string $attribute, mixed $value, Closure $fail): void
    {
        $kind = is_string($value) ? RespondentKind::tryFrom($value) : null;

        if ($kind !== null && resolve(HandlerCoverage::class)->handlerIds($kind, $this->workCenterId()) === []) {
            $fail('Por ahora la empresa no tiene a nadie independiente que reciba una queja sobre esta opción. Use la otra vía que se muestra en la página.');
        }
    }
}
