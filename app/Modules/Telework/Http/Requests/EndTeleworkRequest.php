<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Enums\EndReason;
use App\Modules\Telework\Models\Teleworker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The end of a telework period: why, from which day (not in the future, nor before it started) and an optional note.
 */
final class EndTeleworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-telework') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $zone = config()->string('app.display_timezone');
        $id = $this->route('teleworker');
        $started = is_string($id) && Str::isUuid($id) ? Teleworker::query()->find($id)?->started_at : null;

        return [
            'reason' => ['required', Rule::enum(EndReason::class)],
            'ended_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::now($zone)->toDateString(), ...($started === null ? [] : ['after_or_equal:'.$started->setTimezone($zone)->toDateString()])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function reason(): EndReason
    {
        return $this->enum('reason', EndReason::class) ?? EndReason::Other;
    }

    /** Now when it ends today; the start of that day, in the company's time zone, otherwise. */
    public function endedAt(): CarbonImmutable
    {
        $zone = config()->string('app.display_timezone');
        $day = CarbonImmutable::createFromFormat('Y-m-d', $this->string('ended_on')->toString(), $zone)?->startOfDay() ?? CarbonImmutable::now();

        return $day->isSameDay(CarbonImmutable::now($zone)) ? CarbonImmutable::now() : $day->utc();
    }

    public function note(): ?string
    {
        $note = trim($this->string('note')->toString());

        return $note === '' ? null : $note;
    }
}
