<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Enums\EquipmentKind;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An item of equipment delivered to a person: what it is, a description and the day it was delivered (not in the future).
 */
final class EquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-telework') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(EquipmentKind::class)],
            'description' => ['required', 'string', 'max:500'],
            'delivered_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['kind' => 'tipo de equipo', 'delivered_on' => 'fecha de entrega'];
    }

    public function kind(): EquipmentKind
    {
        return $this->enum('kind', EquipmentKind::class) ?? EquipmentKind::Other;
    }

    public function deliveredOn(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $this->string('delivered_on')->toString())?->startOfDay() ?? CarbonImmutable::now();
    }
}
