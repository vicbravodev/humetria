<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Requests;

use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Support\HandlerCoverage;
use App\Modules\Complaints\Support\HeldCase;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** With whom a handler shares the case: another handler the conflict leaves, who does not have it yet. */
final class ShareCaseRequest extends FormRequest
{
    private ?Complaint $case = null;

    public function authorize(): bool
    {
        $this->case();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['user_id' => ['required', 'integer', $this->mayHaveIt(...)]];
    }

    public function case(): Complaint
    {
        $id = $this->route('complaint');

        return $this->case ??= resolve(HeldCase::class)->of($this, is_string($id) ? $id : '');
    }

    public function userId(): int
    {
        return $this->integer('user_id');
    }

    /** @param  Closure(string): mixed  $fail */
    private function mayHaveIt(string $attribute, mixed $value, Closure $fail): void
    {
        $case = $this->case();
        $eligible = resolve(HandlerCoverage::class)->handlerIds($case->respondent_kind, $case->work_center_id);
        $has = in_array($this->integer('user_id'), ComplaintAssignment::barredFrom($case->id), true);

        if (! in_array($this->integer('user_id'), $eligible, true) || $has) {
            $fail('Esa persona no puede recibir este caso: debe atender quejas, no estar en conflicto con ella, no tenerlo ya ni haberse excusado de él.');
        }
    }
}
