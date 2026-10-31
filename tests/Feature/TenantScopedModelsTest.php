<?php

declare(strict_types=1);

use App\Concerns\BelongsToCurrentTenant;
use App\Models\AuditEntry;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Models whose table has a tenant_id but must be read across companies, each with its reason.
 */
const TENANT_MODELS_WITHOUT_SCOPE = [
    // Who belongs to which company: read before any company is chosen (the switcher, ResolveTenant itself).
    TenantMembership::class => 'resolves the company of a request',
    // An invitation is accepted by someone who is not in the company yet, by its token.
    TenantInvitation::class => 'accepted from outside the company',
    // The audit trail is the superadmin's too, across companies, and some entries have no company.
    AuditEntry::class => 'read across companies by the superadmin',
];

/** @return list<class-string<Model>> */
function modelClasses(): array
{
    $root = dirname(__DIR__, 2).'/';
    $files = [...glob($root.'app/Models/*.php') ?: [], ...glob($root.'app/Modules/*/Models/*.php') ?: []];
    $classes = array_map(fn (string $file): string => 'App\\'.str_replace(['/', '.php'], ['\\', ''], substr($file, strlen($root.'app/'))), $files);

    return array_values(array_filter($classes, fn (string $class): bool => is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract()));
}

it('scopes to the current company every model whose table has a tenant_id', function (): void {
    $unscoped = array_values(array_filter(
        modelClasses(),
        fn (string $class): bool => ! array_key_exists($class, TENANT_MODELS_WITHOUT_SCOPE)
            && Schema::hasColumn((new $class)->getTable(), 'tenant_id')
            && ! in_array(BelongsToCurrentTenant::class, class_uses_recursive($class), true),
    ));

    expect($unscoped)->toBe([]);
});

it('allows an unscoped model only while its table still has a tenant_id', function (): void {
    foreach (array_keys(TENANT_MODELS_WITHOUT_SCOPE) as $class) {
        expect(Schema::hasColumn((new $class)->getTable(), 'tenant_id'))->toBeTrue("{$class} needs no exception")
            ->and(class_uses_recursive($class))->not->toContain(BelongsToCurrentTenant::class);
    }
});
