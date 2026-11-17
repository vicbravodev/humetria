<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The row under the overview of the results holds two optional cards: the Guía I (only with view-trauma-screenings)
 * and the Programa (only when the norm asks for one or it exists). The page lays the row out by how many come; these
 * are the four ways it can arrive.
 */

it('sends the Guía I card only to who may see it, and the Programa only when the norm asks for one', function (TenantRole $role, bool $risk, bool $trauma, bool $program): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $campaign = closedEvaluationWith($tenant, $risk ? nom035Answers('nom035.guide-ii', 'always') : nom035HealthyAnswers('nom035.guide-ii'));

    $this->actingAs(memberOf($tenant, $role))
        ->get(route('tenant.campaigns.results', [$tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('trauma', fn ($counts): bool => ($counts !== null) === $trauma)
            ->where('program', fn ($card): bool => ($card !== null) === $program));
})->with([
    'both: HR, with a Programa asked' => [TenantRole::Hr, true, true, true],
    'only the Guía I: HR, nobody in risk' => [TenantRole::Hr, false, true, false],
    'only the Programa: a viewer, with a Programa asked' => [TenantRole::Viewer, true, false, true],
    'neither: a viewer, nobody in risk' => [TenantRole::Viewer, false, false, false],
]);
