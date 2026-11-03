<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Requests;

use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\MemberSummary;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** Who is designated, and for the whole company or for which work center. */
final class DesignateHandlerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-complaint-channel') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', $this->canHandle(...), $this->notYetDesignated(...)],
            'work_center_id' => ['nullable', 'uuid', $this->isActiveCenter(...)],
        ];
    }

    public function userId(): int
    {
        return $this->integer('user_id');
    }

    /** The work center the designation is limited to; null for the whole company. */
    public function workCenterId(): ?string
    {
        return $this->filled('work_center_id') ? $this->string('work_center_id')->toString() : null;
    }

    /** @param  Closure(string): mixed  $fail */
    private function canHandle(string $attribute, mixed $value, Closure $fail): void
    {
        $ids = array_map(fn (MemberSummary $member): int => $member->userId, resolve(Members::class)->complaintHandlers());

        if (! (is_int($value) || (is_string($value) && ctype_digit($value))) || ! in_array((int) $value, $ids, true)) {
            $fail('Esa persona no puede atender quejas: debe ser de la empresa y tener un rol que lo permita.');
        }
    }

    /** @param  Closure(string): mixed  $fail */
    private function notYetDesignated(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = ComplaintHandler::query()->whereNull('ended_at')->where('user_id', $this->integer('user_id'))->where('work_center_id', $this->workCenterId())->exists();

        if ($exists) {
            $fail('Esa persona ya atiende quejas con ese alcance.');
        }
    }

    /** @param  Closure(string): mixed  $fail */
    private function isActiveCenter(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || resolve(WorkCenters::class)->findActive($value) === null) {
            $fail('Ese centro de trabajo no existe o está archivado.');
        }
    }
}
