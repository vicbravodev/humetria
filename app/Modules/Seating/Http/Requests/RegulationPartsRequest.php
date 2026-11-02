<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use App\Modules\Seating\Data\RegulationParts;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What RH edited in the draft of the regulation: the four parts, none of them empty.
 */
final class RegulationPartsRequest extends FormRequest
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
        return array_fill_keys(['risk_factors', 'pauses', 'seat_location', 'usage_rule'], ['required', 'string', 'max:6000']);
    }

    public function parts(): RegulationParts
    {
        return new RegulationParts(
            trim($this->string('risk_factors')->toString()),
            trim($this->string('pauses')->toString()),
            trim($this->string('seat_location')->toString()),
            trim($this->string('usage_rule')->toString()),
        );
    }
}
