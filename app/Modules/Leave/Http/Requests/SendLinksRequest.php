<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RH sends the link of «Mis vacaciones» to some people: owner and hr. Whoever is not of the company is left out later.
 */
final class SendLinksRequest extends FormRequest
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
        return ['employees' => ['required', 'array', 'min:1', 'max:200'], 'employees.*' => ['required', 'uuid']];
    }

    /** @return list<string> */
    public function employeeIds(): array
    {
        return array_values(array_filter($this->array('employees'), is_string(...)));
    }

    public function userId(): int
    {
        return $this->user()->id ?? abort(403);
    }
}
