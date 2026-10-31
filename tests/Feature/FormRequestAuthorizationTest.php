<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

/*
 * A form request validates before the controller runs: without its own authorize(), whoever may not act would get the
 * validation errors (a 422 that also tells a record exists) instead of the 403.
 */

it('refuses a viewer before validating what they sent', function (string $method, string $route, array $parameters): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(memberOf($tenant, TenantRole::Viewer))
        ->from('/')
        ->{$method}(route($route, [$tenant, ...$parameters]), [])
        ->assertForbidden()
        ->assertSessionHasNoErrors();
})->with([
    'add an employee' => ['post', 'tenant.employees.store', []],
    'edit an employee' => ['put', 'tenant.employees.update', ['employee' => '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']],
    'add a work center' => ['post', 'tenant.work-centers.store', []],
    'edit a work center' => ['put', 'tenant.work-centers.update', ['workCenter' => '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']],
    'invite a member' => ['post', 'tenant.members.invitations.store', []],
    'change a member\'s role' => ['put', 'tenant.members.update', ['member' => '4242']],
    'create an evaluation' => ['post', 'tenant.campaigns.store', []],
    'launch a Guía I campaign' => ['post', 'tenant.campaigns.trauma.store', []],
    'launch a campaign' => ['post', 'tenant.campaigns.launch', ['campaign' => '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']],
    'extend a campaign' => ['post', 'tenant.campaigns.extend', ['campaign' => '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']],
    'draw replacements' => ['post', 'tenant.campaigns.replacements', ['campaign' => '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']],
    'complete the report' => ['put', 'tenant.campaigns.report.update', ['campaign' => '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']],
    'share the report' => ['post', 'tenant.campaigns.shares.store', ['campaign' => '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']],
    'change the NOM-037 settings' => ['put', 'tenant.telework.settings.update', []],
    'publish a NOM-037 document' => ['post', 'tenant.telework.documents.store', []],
    'record a NOM-037 training' => ['post', 'tenant.telework.trainings.store', []],
    'register a teleworker' => ['post', 'tenant.telework.teleworkers.store', ['workCenter' => '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']],
    'form a Comisión' => ['post', 'tenant.telework.committee.store', ['workCenter' => '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']],
    'assign a Comisión member' => ['post', 'tenant.telework.committee.members.store', ['workCenter' => '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']],
    'end a telework period' => ['post', 'tenant.telework.teleworkers.end', ['teleworker' => '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']],
    'hand over equipment' => ['post', 'tenant.telework.teleworkers.equipment.store', ['teleworker' => '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']],
    'record manual acknowledgements' => ['post', 'tenant.telework.documents.acknowledgements.store', ['document' => '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']],
]);
