<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A zone of a center: its name, its center (latitude and longitude) and its radius in meters, as the database checks them. */
final class ZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-attendance') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_m' => ['required', 'integer', 'between:10,5000'],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->trim()->toString();
    }

    public function latitude(): float
    {
        return $this->float('latitude');
    }

    public function longitude(): float
    {
        return $this->float('longitude');
    }

    public function radius(): int
    {
        return $this->integer('radius_m');
    }
}
