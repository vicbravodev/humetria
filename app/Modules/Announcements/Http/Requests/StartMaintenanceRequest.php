<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Http\Requests;

use App\Modules\Announcements\Models\MaintenancePeriod;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** The reason, and the word typed, so nobody stops every company's panel by a slip of the hand. */
final class StartMaintenanceRequest extends FormRequest
{
    public const string WORD = 'MANTENIMIENTO';

    public function authorize(): bool
    {
        // Throws the 404 of the gate: a person who is not the superadmin must not even learn the body was invalid.
        Gate::authorize('manage-announcements');

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
                if (MaintenancePeriod::query()->whereNull('ended_at')->exists()) {
                    $fail('La plataforma ya está en mantenimiento.');
                }
            }],
            'confirmation' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== self::WORD) {
                    $fail('Escribe '.self::WORD.' para confirmar.');
                }
            }],
        ];
    }

    public function reason(): string
    {
        return $this->string('reason')->toString();
    }
}
