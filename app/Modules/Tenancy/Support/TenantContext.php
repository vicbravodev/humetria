<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Data\CompanyBrand;
use App\Modules\Tenancy\Exceptions\NoCurrentTenant;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Context;
use Spatie\Permission\PermissionRegistrar;

/**
 * The company of the current request. ResolveTenant sets it; the rest of the app reads it as CurrentTenant.
 * It lives in Laravel's Context, which is per request and travels inside every job the request queues, so a job works
 * in the company that dispatched it; the same key tags each log line. It is also Spatie's team, so roles and
 * permissions are read inside the company.
 */
final readonly class TenantContext implements CurrentTenant
{
    private const string KEY = 'tenant_id';

    /** Whether the company is the demo, kept beside it (hidden: no log line carries it) so asking costs no query. */
    public const string DEMO = 'tenant_is_demo';

    /** The company's brand, kept beside it (hidden) so every page asks for it at no query. */
    public const string BRAND = 'tenant_brand';

    /** The request the brand was kept in (its object id): a page shows the brand only of a company its own request resolved. */
    public const string BRAND_REQUEST = 'tenant_brand_request';

    public function __construct(private PermissionRegistrar $registrar) {}

    public function set(Tenant $tenant): void
    {
        Context::add(self::KEY, $tenant->id);
        Context::addHidden(self::DEMO, $tenant->is_demo);
        Context::addHidden(self::BRAND, CompanyBrand::of(CompanyDirectory::summaryOf($tenant)));
        Context::addHidden(self::BRAND_REQUEST, spl_object_id(request()));
        $this->registrar->setPermissionsTeamId($tenant->id);
        // The company's minimum and its modules come with the company already read, so asking for them costs no query of its own.
        resolve(CompanyGroupMinimums::class)->prime($tenant);
        resolve(ModuleStates::class)->prime($tenant);
    }

    public function id(): string
    {
        $id = Context::get(self::KEY);

        return is_string($id) ? $id : throw new NoCurrentTenant;
    }
}
