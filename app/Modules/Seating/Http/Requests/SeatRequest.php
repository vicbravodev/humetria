<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use App\Modules\Seating\Data\SeatData;
use App\Modules\Seating\Enums\SeatCondition;
use App\Modules\Seating\Enums\SeatFeature;
use App\Modules\Seating\Enums\SeatKind;
use App\Modules\Seating\Enums\SeatUse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * A seat or a group of equal seats: kind, use, how many, where, the nine characteristics, its condition and an optional
 * photo. Whether the position is of the center, and that a rest area has its location, are the action's to say.
 */
final class SeatRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(SeatKind::class)],
            'use' => ['required', Rule::enum(SeatUse::class)],
            'quantity' => ['required', 'integer', 'between:1,1000'],
            'location' => ['nullable', 'string', 'max:500'],
            'position_id' => ['nullable', 'uuid'],
            ...array_fill_keys(array_map(fn (SeatFeature $feature): string => $feature->value, SeatFeature::cases()), ['required', 'boolean']),
            'condition' => ['required', Rule::enum(SeatCondition::class)],
            'signposted' => ['required', 'boolean'],
            // mimetypes reads the file's content, not its extension.
            'photo' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png', 'max:'.config()->integer('seating.max_photo_kb')],
        ];
    }

    public function toData(?string $id, string $workCenterId): SeatData
    {
        $location = trim($this->string('location')->toString());

        return new SeatData(
            id: $id,
            workCenterId: $workCenterId,
            positionId: $this->filled('position_id') ? $this->string('position_id')->toString() : null,
            location: $location === '' ? null : $location,
            kind: SeatKind::from($this->string('kind')->toString()),
            use: SeatUse::from($this->string('use')->toString()),
            quantity: $this->integer('quantity'),
            characteristics: array_combine(
                array_map(fn (SeatFeature $feature): string => $feature->value, SeatFeature::cases()),
                array_map(fn (SeatFeature $feature): bool => $this->boolean($feature->value), SeatFeature::cases()),
            ),
            condition: SeatCondition::from($this->string('condition')->toString()),
            signposted: $this->boolean('signposted'),
        );
    }

    /** Where the uploaded photo is, if one came. */
    public function photoFile(): ?string
    {
        $photo = $this->file('photo');

        return $photo instanceof UploadedFile ? $photo->getPathname() : null;
    }
}
