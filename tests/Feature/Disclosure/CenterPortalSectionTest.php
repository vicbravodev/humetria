<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\IssuePageLink;
use App\Modules\Disclosure\Support\CenterPortalSection;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

mutates(CenterPortalSection::class);

// R2-employee-07: from the portal, the person lands on their own center's page, not on the list of every center.
it('opens the page of the center of the person who comes from the portal', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($tenant);
    WorkCenter::factory()->for($tenant)->create();
    $mine = WorkCenter::factory()->for($tenant)->create();
    $person = Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $mine->id]);
    $link = resolve(IssuePageLink::class)->handle(memberOf($tenant, TenantRole::Hr)->id);
    $token = (string) $link->token();

    $section = resolve(CenterPortalSection::class)->for(new PortalIdentity($tenant->id, $person->id, 'Acme', $person->name), 'portal-token');

    expect($section?->href)->toBe(route('workers.center', [$token, $mine->id]));
});
