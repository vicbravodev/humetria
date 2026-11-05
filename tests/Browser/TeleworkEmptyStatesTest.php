<?php

declare(strict_types=1);

use App\Modules\Telework\Actions\RequestAcknowledgements;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;

// Rediseño v2 (C7): a list with nobody in its view says why, and «Nadie coincide» is kept for a search that found nobody.
it('tells an empty view of the confirmations apart from a search that found nobody', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $center = WorkCenter::factory()->for($tenant)->create();
    EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz']);
    $document = TeleworkDocument::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'kind' => DocumentKind::Policy]);
    workIn($tenant);
    resolve(RequestAcknowledgements::class)->handle([$document->id]);
    $page = "{$url}/telework/documents/{$document->id}/acknowledgements";

    visit("{$page}?status=acknowledged")
        ->assertSee('Nadie ha confirmado todavía')
        ->assertDontSee('Nadie coincide')
        ->assertNoJavaScriptErrors();

    visit("{$page}?q=Zeta")
        ->assertSee('Nadie coincide')
        ->assertNoJavaScriptErrors();
});
