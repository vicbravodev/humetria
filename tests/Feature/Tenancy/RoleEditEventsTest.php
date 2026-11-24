<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Actions\UpdateCompanyRole;
use App\Modules\Tenancy\Events\MemberPermissionsChanged;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

mutates(UpdateCompanyRole::class);

// Editing a role tells its people only when their permissions change, and reads who they are once.
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    $this->role = app(CreateCompanyRole::class)->handle($this->tenant, 'Auditoría', ['view-dashboard'], null, $this->owner);
    $this->holders = User::factory()->count(2)->create();

    foreach ($this->holders as $person) {
        DB::table('model_has_roles')->insert(['role_id' => $this->role->id, 'model_type' => $person->getMorphClass(), 'model_id' => $person->id, 'tenant_id' => $this->tenant->id]);
    }

    Event::fake([MemberPermissionsChanged::class]);
});

it('tells nobody when only the label changes', function (): void {
    app(UpdateCompanyRole::class)->handle($this->role, 'Auditoría interna', ['view-dashboard'], $this->owner);

    Event::assertNotDispatched(MemberPermissionsChanged::class);
});

it('tells each holder once when the permissions change, reading the holders once', function (): void {
    DB::enableQueryLog();
    app(UpdateCompanyRole::class)->handle($this->role, 'Auditoría', ['view-dashboard', 'manage-organization'], $this->owner);
    $reads = collect(DB::getQueryLog())->filter(fn (array $log): bool => str_starts_with((string) $log['query'], 'select "model_id" from "model_has_roles"'));
    DB::disableQueryLog();

    Event::assertDispatchedTimes(MemberPermissionsChanged::class, 2);
    foreach ($this->holders as $person) {
        Event::assertDispatched(MemberPermissionsChanged::class, fn (MemberPermissionsChanged $event): bool => $event->userId === $person->id && $event->tenantId === $this->tenant->id);
    }
    expect($reads)->toHaveCount(1);
});
