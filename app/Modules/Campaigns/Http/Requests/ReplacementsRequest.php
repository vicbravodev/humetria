<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReplacementsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['count' => ['required', 'integer', 'min:1', 'max:1000']];
    }

    public function count(): int
    {
        return $this->integer('count');
    }
}
