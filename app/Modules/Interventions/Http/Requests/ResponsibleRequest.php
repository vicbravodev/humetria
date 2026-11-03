<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * HR says who answers for the Programa: a member of the company or a free name.
 */
final class ResponsibleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-interventions') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'responsible_user_id' => ['nullable', 'integer'],
            'responsible_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function userId(): ?int
    {
        return $this->filled('responsible_user_id') ? $this->integer('responsible_user_id') : null;
    }

    public function name(): ?string
    {
        return $this->filled('responsible_name') ? $this->string('responsible_name')->toString() : null;
    }
}
