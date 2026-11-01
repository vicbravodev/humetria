<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Data\AddressData;
use App\Modules\Telework\Enums\NoticeKind;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use UnexpectedValueException;

/**
 * A notice as the person writes it in their portal (numerals 6.3, 6.7, 6.9): kind, written date, date it happened and
 * description; neither date in the future. The person is the owner of the link, never typed.
 */
final class NoticeRequest extends FormRequest
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
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString();

        return [
            'kind' => ['required', Rule::enum(NoticeKind::class)],
            'written_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'description' => ['required', 'string', 'max:3000'],
            'traumatic_event' => ['sometimes', 'boolean'],
            'temporary' => ['sometimes', 'boolean'],
            ...($this->kind() === NoticeKind::AddressChange ? AddressRules::for() : []),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['kind' => 'tipo de aviso', 'written_on' => 'fecha de elaboración', 'occurred_on' => 'fecha en que ocurrió', 'description' => 'descripción', 'traumatic_event' => 'acontecimiento traumático', 'temporary' => 'cambio temporal'];
    }

    public function kind(): NoticeKind
    {
        return $this->enum('kind', NoticeKind::class) ?? NoticeKind::WorkRisk;
    }

    /** The new address, only for an address change. */
    public function newAddress(): ?AddressData
    {
        return $this->kind() === NoticeKind::AddressChange ? AddressData::fromInput($this->only(array_keys(AddressRules::for()))) : null;
    }

    /** Whether the change is temporary: only an address change can be. */
    public function temporary(): bool
    {
        return $this->kind() === NoticeKind::AddressChange && $this->boolean('temporary');
    }

    /** Whether it is a severe traumatic event: only a change of conditions can be. */
    public function traumaticEvent(): bool
    {
        return $this->kind() === NoticeKind::ConditionChange && $this->boolean('traumatic_event');
    }

    public function writtenOn(): CarbonImmutable
    {
        return $this->day('written_on');
    }

    public function occurredOn(): CarbonImmutable
    {
        return $this->day('occurred_on');
    }

    private function day(string $key): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $this->string($key)->toString())?->startOfDay() ?? throw new UnexpectedValueException("The {$key} date passed validation but cannot be read.");
    }
}
