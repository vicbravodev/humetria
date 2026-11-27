<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\IssuePageLink;
use App\Modules\Disclosure\Support\CenterWorkerSection;
use App\Modules\Disclosure\Support\PageUrl;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use Carbon\CarbonImmutable;

mutates(CenterWorkerSection::class, PageUrl::class);

// R2-employee-07: from their start, the person lands on their own center's page, not on the list of every center.
it('opens the page of the center of the person who comes from their start, while its link is active and the company has NOM-035', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($tenant);
    WorkCenter::factory()->for($tenant)->create();
    $mine = WorkCenter::factory()->for($tenant)->create();
    $person = Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $mine->id]);
    $section = fn () => resolve(CenterWorkerSection::class)->for(new WorkerIdentity($tenant->id, $person->id, 'Acme', $person->name));

    expect($section())->toBeNull();

    $link = resolve(IssuePageLink::class)->handle(memberOf($tenant, TenantRole::Hr)->id);

    expect($section()?->key)->toBe('center')
        ->and($section()?->label)->toBe('Avisos y resultados de su centro')
        ->and($section()?->href)->toBe(route('workers.center', [(string) $link->token(), $mine->id]));

    $link->forceFill(['disabled_at' => now()])->save();
    expect($section())->toBeNull();

    $link->forceFill(['disabled_at' => null])->save();
    resolve(CompanyModules::class)->syncPlan($tenant->id, ['seating'], CarbonImmutable::now(config('app.display_timezone')));
    expect($section())->toBeNull();
});
