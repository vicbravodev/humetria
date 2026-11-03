<?php

declare(strict_types=1);

use App\Modules\Organization\Actions\FindOrCreateJobGrade;
use App\Modules\Organization\Contracts\JobGrades;
use App\Modules\Organization\Data\JobGradeData;
use App\Modules\Organization\Http\Controllers\JobGradeOptionsController;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Organization\Support\CompanyJobGrades;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

mutates(CompanyJobGrades::class, FindOrCreateJobGrade::class, JobGradeOptionsController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
});

it('stores job grades with a random UUID v4', function (): void {
    expect(Str::isUuid(JobGrade::factory()->create()->id, 4))->toBeTrue();
});

it('allows one job grade per category and level in a company, whatever the case', function (): void {
    JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);

    expect(fn () => DB::transaction(fn () => JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'OPERADOR', 'level' => 'a'])))
        ->toThrow(QueryException::class, 'job_grades_tenant_category_level_unique');
});

it('allows the same category and level in another company, and another level in the same one', function (): void {
    JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
    JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'B']);
    JobGrade::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'category' => 'Operador', 'level' => 'A']);

    expect(JobGrade::query()->withoutGlobalScopes()->count())->toBe(3);
});

it('refuses a blank category or level', function (string $column): void {
    expect(fn () => DB::transaction(fn () => JobGrade::factory()->create(['tenant_id' => $this->tenant->id, $column => '  '])))->toThrow(QueryException::class);
})->with(['category', 'level']);

it('keeps job grades apart per company', function (): void {
    $mine = JobGrade::factory()->create(['tenant_id' => $this->tenant->id]);
    $foreign = JobGrade::factory()->create();
    workIn($this->tenant);

    expect(JobGrade::query()->pluck('id')->all())->toBe([$mine->id])
        ->and(JobGrade::query()->whereKey($foreign->id)->update(['level' => 'Robado']))->toBe(0)
        ->and(JobGrade::query()->withoutGlobalScopes()->findOrFail($foreign->id)->level)->not->toBe('Robado');
});

it('reuses the job grade a company has, whatever the case, and creates the first time', function (): void {
    $existing = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
    JobGrade::factory()->create(['category' => 'Operador', 'level' => 'Z']);
    workIn($this->tenant);
    Log::spy();

    $same = resolve(FindOrCreateJobGrade::class)->handle(' OPERADOR ', 'a');
    $created = resolve(FindOrCreateJobGrade::class)->handle('Operador', 'Z');

    expect($same->id)->toBe($existing->id)
        ->and($created->only(['tenant_id', 'category', 'level']))->toBe(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'Z']);
    Log::shouldHaveReceived('info')->once()->with('job_grade.created', ['job_grade_id' => $created->id]);
});

it('reads the company\'s job grades through the contract, a page at a time, by category and level', function (): void {
    foreach ([['Técnico', 'B'], ['Operador', 'A'], ['Operador', 'B']] as [$category, $level]) {
        JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => $category, 'level' => $level]);
    }
    JobGrade::factory()->create(['category' => 'Operador', 'level' => 'C']);
    workIn($this->tenant);
    $grades = resolve(JobGrades::class);

    $page = $grades->page('', 2);
    $found = $grades->page('tecnico', 10);

    expect($page->total())->toBe(3)
        ->and(array_map(fn (JobGradeData $grade): string => "{$grade->category} {$grade->level}", $page->items()))->toBe(['Operador A', 'Operador B'])
        ->and(array_map(fn (JobGradeData $grade): string => "{$grade->category} {$grade->level}", $found->items()))->toBe(['Técnico B']);
});

it('finds a job grade by id inside the company only', function (): void {
    $mine = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
    $foreign = JobGrade::factory()->create();
    workIn($this->tenant);
    $grades = resolve(JobGrades::class);

    expect($grades->find($mine->id))->toEqual(new JobGradeData($mine->id, 'Operador', 'A'))
        ->and($grades->find($foreign->id))->toBeNull()
        ->and($grades->findMany([$mine->id, $foreign->id]))->toEqual([$mine->id => new JobGradeData($mine->id, 'Operador', 'A')])
        ->and($grades->findMany([]))->toBe([]);
});

it('finds the company\'s categories and levels by name for any member, ignoring case and accents, at most 20, without repeats', function (): void {
    foreach ([['Técnico', 'A'], ['Técnico', 'B'], ['Operador', 'A'], ['Operador', 'Senior']] as [$category, $level]) {
        JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => $category, 'level' => $level]);
    }
    JobGrade::factory()->create(['category' => 'Técnico de otra empresa', 'level' => 'Q']);
    $viewer = memberOf($this->tenant, TenantRole::Viewer);

    $this->actingAs($viewer)
        ->getJson(route('tenant.job-grades.options', [$this->tenant, 'category', 'q' => ' TECNICO ']))
        ->assertOk()
        ->assertExactJson([['value' => 'Técnico', 'label' => 'Técnico']]);

    $this->actingAs($viewer)
        ->getJson(route('tenant.job-grades.options', [$this->tenant, 'level']))
        ->assertExactJson([['value' => 'A', 'label' => 'A'], ['value' => 'B', 'label' => 'B'], ['value' => 'Senior', 'label' => 'Senior']]);

    $this->actingAs($viewer)
        ->getJson(route('tenant.job-grades.options', [$this->tenant, 'level', 'q' => '%']))
        ->assertExactJson([]);
});

it('gives at most 20 options', function (): void {
    JobGrade::factory()->count(21)->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->getJson(route('tenant.job-grades.options', [$this->tenant, 'category']))
        ->assertJsonCount(20);
});

it('answers the options only to members of the company and for a known field', function (): void {
    $this->getJson(route('tenant.job-grades.options', [$this->tenant, 'category']))->assertUnauthorized();
    $this->actingAs(memberOf(Tenant::factory()->create(), TenantRole::Owner))
        ->getJson(route('tenant.job-grades.options', [$this->tenant, 'category']))
        ->assertNotFound();
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner))
        ->getJson('/admin/acme/job-grades/salary/options')
        ->assertNotFound();
});
