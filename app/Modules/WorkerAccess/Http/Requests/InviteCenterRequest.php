<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The center whose people without a key RH invites: owner and hr only. Whether it is one in use is the action's to say. */
final class InviteCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-worker-access') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['work_center_id' => ['required', 'uuid']];
    }

    public function workCenterId(): string
    {
        return $this->string('work_center_id')->toString();
    }
}
