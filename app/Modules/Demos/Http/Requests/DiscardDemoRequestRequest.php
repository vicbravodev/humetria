<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Requests;

use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Why a request is discarded: always said, and never for one that is already a company. */
final class DiscardDemoRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('manage-demo-requests');

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['bail', 'required', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
                $route = $this->route('demoRequest');

                if ($route instanceof DemoRequest && $route->status === DemoStatus::Converted) {
                    $fail('Esta solicitud ya es una empresa.');
                }
            }],
        ];
    }

    public function reason(): string
    {
        return trim($this->string('reason')->toString());
    }
}
