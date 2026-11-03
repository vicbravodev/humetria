<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use App\Modules\Seating\Enums\Sector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The declaration of a work center: its sector, whether nobody works standing there, and an optional note.
 */
final class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-seating') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sector' => ['required', Rule::enum(Sector::class)],
            'no_standing_positions' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function sector(): Sector
    {
        return Sector::from($this->string('sector')->toString());
    }

    public function noStandingPositions(): bool
    {
        return $this->boolean('no_standing_positions');
    }

    public function note(): ?string
    {
        $note = trim($this->string('note')->toString());

        return $note === '' ? null : $note;
    }
}
