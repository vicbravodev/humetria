<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A manager rejects a request from their portal: the reason is required, since the person will read it.
 */
final class PortalDecisionRequest extends FormRequest
{
    /** The session of the person is the credential (RequireWorkerSession): the controller asks it who they are. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:500']];
    }
}
