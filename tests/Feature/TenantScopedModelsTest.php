<?php

declare(strict_types=1);

use App\Concerns\BelongsToCurrentTenant;
use App\Models\AuditEntry;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\CompanyTransfer;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentAllocation;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\StatementLine;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionCharge;
use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Billing\Models\UsageEvent;
use App\Modules\Billing\Models\UsageGrant;
use App\Modules\Billing\Models\UsageReservation;
use App\Modules\Compensation\Models\FiscalParameter;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantLimit;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\TenantModule;
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
    // What each company pays and who pays it is read across all of them, by the staff's console, never from inside a company.
    AccountCompany::class => 'read across companies by the billing console',
    Subscription::class => 'read across companies by the billing console',
    SubscriptionCharge::class => 'read across companies by the billing console',
    // The statement of a company is read with the rest of what its account owes, across all the companies it pays.
    Statement::class => 'read across companies by the billing console',
    // What a company has of each module is read from inside it (menu, routes) and from the console across companies.
    TenantModule::class => 'read across companies by the console and by the module gate',
    // The counters of people and centers are read across companies by the console and the nightly reconciliation.
    // The ledger of metered use is read across companies by the console and the expiry of holds, and by key from the caller's company.
    UsageCounter::class => 'read across companies by the console and the expiry of holds',
    UsageReservation::class => 'read across companies by the expiry of holds',
    UsageEvent::class => 'read across companies by the console',
    UsageGrant::class => 'read across companies by the console',
    TenantLimit::class => 'read across companies by the console and the reconciliation',
    // Who paid a company before and why it moved is read across companies, from both accounts' pages.
    CompanyTransfer::class => 'read across companies by the billing console',
];

/**
 * Models that belong to the platform and have no tenant_id at all, each with its reason. They are the same for every
 * company and no account writes them.
 */
const PLATFORM_MODELS = [
    // Maintained by Humetria from official sources (UMA, IMSS, ISN...); an account only reads them through the calculator.
    FiscalParameter::class => 'fiscal and legal values shared by every company',
    // The payer of one or many companies (a consultant's clients): commercial data of Humetría, read by the staff's console.
    BillingAccount::class => 'who pays for the companies, across all of them',
    // Lines belong to a statement and payments to an account: neither belongs to a single company.
    StatementLine::class => 'a line of a statement; the statement carries the company',
    Payment::class => 'money an account paid, for all the companies it pays',
    PaymentAllocation::class => 'the part of a payment that settles a statement',
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

it('keeps the platform models free of a tenant_id', function (): void {
    foreach (array_keys(PLATFORM_MODELS) as $class) {
        expect(Schema::hasColumn((new $class)->getTable(), 'tenant_id'))->toBeFalse("{$class} is platform data")
            ->and(in_array(BelongsToCurrentTenant::class, class_uses_recursive($class), true))->toBeFalse();
    }
});
