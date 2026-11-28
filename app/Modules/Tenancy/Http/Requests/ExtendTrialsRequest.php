<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ExtendTrialsRequest extends FormRequest
{
    use ChoosesCompanies;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [...$this->selectionRules(), 'days' => ['required', 'integer', 'between:1,30']];
    }

    public function days(): int
    {
        return $this->integer('days');
    }
}
