<?php

declare(strict_types=1);

use App\Modules\Telework\Models\WorkplaceReview;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function reviewListCheck(): string
{
    return DB::selectOne("SELECT pg_get_constraintdef(oid) AS def FROM pg_constraint WHERE conname = 'workplace_reviews_checklist_has_application'")->def;
}

it('reverses the migration with remote checks that carry a list stored, and reapplies it', function (): void {
    $migration = require database_path('migrations/2026_11_19_100000_let_visits_and_remote_checks_decide_a_list.php');
    $tenant = Tenant::factory()->create();
    $center = WorkCenter::factory()->for($tenant)->create();
    workIn($tenant);
    $teleworker = TeleworkerFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
    $workplace = WorkplaceFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $teleworker->id]);
    $application = ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $tenant->id, 'workplace_id' => $workplace->id]);
    WorkplaceReview::factory()->create(['tenant_id' => $tenant->id, 'workplace_id' => $application->workplace_id, 'application_id' => $application->id, 'method' => 'remote_check']);

    $migration->down();

    // The strict check is back for new writes, and the remote check already stored is kept.
    expect(reviewListCheck())->toContain('NOT VALID')
        // Its own savepoint, so the refused insert does not abort the test's transaction.
        ->and(fn () => DB::transaction(fn () => WorkplaceReview::factory()->create(['tenant_id' => $tenant->id, 'workplace_id' => $application->workplace_id, 'application_id' => ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $tenant->id, 'workplace_id' => $application->workplace_id])->id, 'method' => 'remote_check'])))
        ->toThrow(QueryException::class);

    $migration->up();

    expect(reviewListCheck())->not->toContain('NOT VALID')->and(WorkplaceReview::query()->count())->toBe(1);
});
