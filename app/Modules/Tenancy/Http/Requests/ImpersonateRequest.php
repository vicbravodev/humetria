<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * "Entrar" from the console: as the owner, or (only in the demo company) as its HR. A real company asks why and for how long (30,
 * 60 or 120 minutes); the demo company, shown to prospects, asks neither and does not end by itself. From the demo room, also
 * the page of the demo to land on (the guide's step).
 */
final class ImpersonateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Throws the 404 of the gate: a person who is not on the staff must not even learn the body was invalid.
        Gate::authorize('view-console');

        // Only the demo company is entered as its HR: a real one answers 404 before anything sent is read.
        abort_if($this->string('as')->toString() === 'hr' && $this->tenant()?->is_demo !== true, 404);

        return true;
    }

    /** How long an entry lasts, in minutes. */
    public const array MINUTES = [30, 60, 120];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $demo = $this->tenant()?->is_demo === true;

        return [
            'as' => ['sometimes', 'string', 'in:owner,hr'],
            'reason' => [$demo ? 'nullable' : 'required', 'string', 'min:3', 'max:500'],
            'minutes' => [$demo ? 'nullable' : 'required', 'integer', Rule::in(self::MINUTES)],
            'to' => ['sometimes', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['minutes' => 'duración'];
    }

    /**
     * The platform's messages for `minutes` speak of a work schedule; here it is how long the entry lasts.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'minutes.required' => 'Elige cuánto tiempo vas a estar dentro.',
            'minutes.integer' => 'Elige 30 minutos, 1 hora o 2 horas.',
            'minutes.in' => 'Elige 30 minutos, 1 hora o 2 horas.',
        ];
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }

    /** How long the entry lasts; null for the demo company, which is shown to prospects and never ends by itself. */
    public function minutes(): ?int
    {
        return $this->tenant()?->is_demo === true ? null : $this->integer('minutes');
    }

    private function tenant(): ?Tenant
    {
        $tenant = $this->route('tenant');

        return $tenant instanceof Tenant ? $tenant : null;
    }

    public function role(): TenantRole
    {
        return $this->string('as')->toString() === 'hr' ? TenantRole::Hr : TenantRole::Owner;
    }

    /**
     * The page to land on: only a path inside the company entered (`/admin/{slug}`, whole segment), and only in the demo
     * company, whose room asks for it. Anything else (another company, another site, a way out of it) lands on the dashboard.
     */
    public function landing(string $slug, bool $demo): ?string
    {
        $to = $this->string('to')->toString();
        $inside = preg_match('#\A/admin/'.preg_quote($slug, '#').'(?:[/?]|\z)#', $to) === 1;
        // A dot-segment, plain or percent-encoded in any case (`%2e`): browsers resolve both before asking.
        $plain = preg_match('#[\x00-\x1F\x7F\\\\]|/(?:\.|%2e){1,2}(?:[/?]|\z)#i', $to) === 0;

        return $demo && $inside && $plain ? $to : null;
    }
}
