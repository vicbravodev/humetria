<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use App\Modules\Attendance\Data\Position;
use App\Modules\Attendance\Enums\MarkType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * A person marks: what (`mark_type`), the signature of their linked device, and, as the device gave them, the code of the
 * center's screen (`q`) and its position (`lat`, `lng`, `accuracy` in meters, all three or none). The signature is the
 * credential; there is no account to authorize. The time is never read from here: the server stamps it.
 */
final class MarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mark_type' => ['required', Rule::enum(MarkType::class)],
            'credential' => ['required', 'array'],
            'q' => ['nullable', 'string', 'max:128'],
            'lat' => ['nullable', 'required_with:lng,accuracy', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat,accuracy', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'required_with:lat,lng', 'numeric', 'min:0'],
        ];
    }

    public function type(): MarkType
    {
        // Validated as required and one of the types.
        return $this->enum('mark_type', MarkType::class) ?? throw new LogicException('The type was not validated.');
    }

    /**
     * @return array<mixed>
     */
    public function credential(): array
    {
        return $this->array('credential');
    }

    public function qr(): ?string
    {
        return $this->filled('q') ? $this->string('q')->toString() : null;
    }

    public function position(): ?Position
    {
        return $this->filled('lat') ? new Position($this->float('lat'), $this->float('lng'), $this->float('accuracy')) : null;
    }
}
