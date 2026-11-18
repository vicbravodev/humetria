<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Contracts\GroupMinimums;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** The company's minimum of people per group, from 1 to 50; empty goes back to the platform's. Only the owner sets it. */
final class GroupMinimumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-members');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['min_group' => ['present', 'nullable', 'integer', 'min:'.GroupMinimums::LOWEST, 'max:'.GroupMinimums::HIGHEST]];
    }

    public function minimum(): ?int
    {
        return $this->filled('min_group') ? $this->integer('min_group') : null;
    }
}
