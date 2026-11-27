<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Enums\TenantRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * "Entrar" from the console: as the owner, or (only in the demo company) as its HR; from the demo room, also the page of
 * the demo to land on (the guide's step).
 */
final class ImpersonateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Throws the 404 of the gate: a person who is not on the staff must not even learn the body was invalid.
        Gate::authorize('view-console');

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'as' => ['sometimes', 'string', 'in:owner,hr'],
            'to' => ['sometimes', 'string', 'max:2048'],
        ];
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
