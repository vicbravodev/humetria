<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Requests;

use App\Modules\WorkerAccess\Contracts\WorkerSession;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The app asks for a new pass for its device key when the person opens their start with a session (plan B of the offline marks).
 */
final class RenewPassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return resolve(WorkerSession::class)->current() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['device_key_id' => ['required', 'uuid']];
    }
}
