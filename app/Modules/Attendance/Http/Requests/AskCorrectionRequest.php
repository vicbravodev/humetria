<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use App\Modules\Attendance\Actions\RequestCorrection;
use App\Modules\Attendance\Enums\MarkType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * A person asks RH to correct their record: the signature of their linked device, the mark that is wrong (`mark_id`; none
 * for one they forgot), its type, the time they propose as their clock reads it (`proposed_at`, 'Y-m-d H:i' local) and why,
 * in plain text. `start` names the week to give back.
 */
final class AskCorrectionRequest extends FormRequest
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
        return [
            'credential' => ['required', 'array'],
            'mark_id' => ['nullable', 'uuid'],
            'mark_type' => ['required', Rule::enum(MarkType::class)],
            'proposed_at' => ['required', 'date_format:Y-m-d H:i'],
            'reason' => ['required', 'string', 'min:'.RequestCorrection::MIN_REASON, 'max:'.RequestCorrection::MAX_REASON, 'not_regex:'.RequestCorrection::CONTROL_CHARACTERS],
            'start' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function credential(): array
    {
        return $this->array('credential');
    }

    public function markId(): ?string
    {
        return $this->filled('mark_id') ? $this->string('mark_id')->toString() : null;
    }

    public function type(): MarkType
    {
        return $this->enum('mark_type', MarkType::class) ?? throw new LogicException('The type was not validated.');
    }

    /** The time on the person's clock, read in the display time zone. */
    public function proposedAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d H:i', $this->string('proposed_at')->toString(), config()->string('app.display_timezone'))
            ?: throw new LogicException('The time was not validated.');
    }

    public function reason(): string
    {
        return $this->string('reason')->toString();
    }

    public function start(): ?CarbonImmutable
    {
        return $this->filled('start') ? CarbonImmutable::createFromFormat('!Y-m-d', $this->string('start')->toString()) ?: null : null;
    }
}
