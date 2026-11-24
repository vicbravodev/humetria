<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\PermissionCatalog;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/** What a role of the company's own is made of, for creating and editing one: checked here, before any action runs. */
final class CompanyRoleRules
{
    public const string NAME_TAKEN = 'Ya hay un rol con ese nombre.';

    public const string ACKNOWLEDGE = 'Confirma que quieres dar permisos que abren información sensible.';

    /** How the errors name each field: «label» is a device's name elsewhere. */
    public const array ATTRIBUTES = ['label' => 'nombre del rol', 'permissions' => 'permisos', 'permissions.*' => 'permiso', 'based_on' => 'plantilla'];

    /**
     * @param  bool  $keepsSome  the role already holds permissions it cannot be sent back (of a tool the plan lost): they
     *                           count toward the minimum, so what is sent may be none.
     * @return array<string, list<(Closure(string, mixed, Closure(string): mixed): void)|string>>
     */
    public static function rules(Tenant $tenant, bool $keepsSome = false): array
    {
        return [
            'label' => ['required', 'string', 'max:60', TenantRules::SINGLE_LINE],
            'permissions' => $keepsSome ? ['array'] : ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'distinct', self::permission($tenant)],
            'acknowledge_sensitive' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The name is the company's only once, a template's included, whatever its case; and a sensitive permission switched
     * on asks to be acknowledged.
     *
     * @param  array<int, string>  $held  what the role has now: only what is added asks for the acknowledgement.
     */
    public static function after(Validator $validator, Request $request, Tenant $tenant, ?Role $editing = null, array $held = []): void
    {
        if (! $validator->errors()->has('label') && self::taken($tenant, $request->string('label')->toString(), $editing)) {
            $validator->errors()->add('label', self::NAME_TAKEN);
        }

        self::acknowledged($validator, $request, $held);
    }

    /**
     * A sensitive permission switched on, that was not on before, asks to be acknowledged.
     *
     * @param  array<int, string>  $held
     */
    public static function acknowledged(Validator $validator, Request $request, array $held): void
    {
        $added = array_diff(self::names($request), $held);

        if (! $request->boolean('acknowledge_sensitive') && array_any($added, fn (string $name): bool => PermissionCatalog::find($name)?->sensitive === true)) {
            $validator->errors()->add('acknowledge_sensitive', self::ACKNOWLEDGE);
        }
    }

    /**
     * The permission names sent, once validated.
     *
     * @return list<string>
     */
    public static function names(Request $request): array
    {
        $names = [];

        foreach ($request->array('permissions') as $name) {
            is_string($name) && $names[] = $name;
        }

        return $names;
    }

    /**
     * A permission the company can hand out now; one of a tool the plan lacks says so.
     *
     * @return Closure(string, mixed, Closure(string): mixed): void
     */
    public static function permission(Tenant $tenant): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($tenant): void {
            $permission = is_string($value) ? PermissionCatalog::find($value) : null;

            if ($permission === null || ! in_array($permission, PermissionCatalog::assignable($tenant), true)) {
                $fail('Ese permiso no se puede dar en esta empresa.');
            } elseif (($reason = PermissionCatalog::unavailableReason($tenant, $permission)) !== null) {
                $fail($reason);
            }
        };
    }

    private static function taken(Tenant $tenant, string $label, ?Role $editing): bool
    {
        $label = Str::lower($label);

        return array_any(TenantRole::cases(), fn (TenantRole $role): bool => Str::lower($role->label()) === $label)
            || Role::query()->where('tenant_id', $tenant->id)->whereRaw('lower(label) = ?', [$label])
                ->when($editing !== null, fn ($query) => $query->whereKeyNot($editing?->getKey()))->exists();
    }
}
