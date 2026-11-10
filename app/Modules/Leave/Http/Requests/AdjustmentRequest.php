<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Requests;

use App\Modules\Leave\Enums\Tranche;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RH corrects what is left of a tranche of one period of a person, in favor or against, and says why: owner and hr only.
 */
final class AdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-vacations') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['required', 'uuid'],
            'tranche' => ['required', Rule::enum(Tranche::class)],
            'days' => ['required', 'integer', 'between:-365,365', 'not_in:0'],
            'note' => ['required', 'string', 'max:500'],
        ];
    }

    public function periodId(): string
    {
        return $this->string('period')->toString();
    }

    public function tranche(): Tranche
    {
        return Tranche::from($this->string('tranche')->toString());
    }

    public function days(): int
    {
        return $this->integer('days');
    }

    public function note(): string
    {
        return $this->string('note')->toString();
    }

    public function userId(): int
    {
        return $this->user()->id ?? abort(403);
    }
}
