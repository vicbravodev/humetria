<?php

declare(strict_types=1);

use App\Modules\Assistant\Exceptions\ScopeMismatch;
use App\Modules\Assistant\Tools\GetFirstSteps;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

mutates(GetFirstSteps::class);

beforeEach(function (): void {
    config(['first_steps.enabled' => true]);
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
});

it('gives the owner each step with its state and its link', function (): void {
    $card = toolCard(GetFirstSteps::class, assistantScopeIn($this->a, TenantRole::Owner));

    expect($card['render'])->toBe('list')
        ->and($card['title'])->toBe('Primeros pasos')
        ->and($card['rows'])->toHaveCount(5)
        ->and(array_column($card['rows'], 'kind'))->each->toBe('first_step')
        ->and(array_column($card['rows'], 'detail'))->each->toBe('Pendiente')
        ->and($card['rows'][0]['title'])->toBe('Revisa tus centros de trabajo')
        ->and($card['rows'][0]['link'])->toBe("/admin/{$this->a->slug}/work-centers")
        ->and($card['rows'][1]['title'])->toBe('Carga a tu gente')
        ->and($card['rows'][1]['link'])->toStartWith("/admin/{$this->a->slug}/")
        ->and($card['rows'][4]['title'])->toBe('Conoce a Humi')
        ->and($card['rows'][4]['link'])->toBe("/admin/{$this->a->slug}");
});

it('marks a step done once its data is there', function (): void {
    $scope = assistantScopeIn($this->a, TenantRole::Owner);
    WorkCenter::factory()->create(['tenant_id' => $this->a->id]);

    $rows = toolCard(GetFirstSteps::class, $scope)['rows'];

    expect($rows[0]['detail'])->toBe('Hecho')->and($rows[1]['detail'])->toBe('Pendiente');
});

it('gives nobody else any step', function (TenantRole $role): void {
    $card = toolCard(GetFirstSteps::class, assistantScopeIn($this->a, $role));

    expect($card['rows'])->toBe([])->and($card['note'])->toBe('No tienes primeros pasos pendientes.');
})->with([TenantRole::Hr, TenantRole::Viewer]);

it('never carries a name, an email or a figure of a person', function (): void {
    $scope = assistantScopeIn($this->a, TenantRole::Owner);
    $center = WorkCenter::factory()->create(['tenant_id' => $this->a->id]);
    $employee = Employee::factory()->create(['tenant_id' => $this->a->id, 'work_center_id' => $center->id, 'name' => 'Rosalinda Pérez', 'email' => 'rosalinda@secreta.test']);
    $invited = TenantInvitation::factory()->create(['tenant_id' => $this->a->id, 'email' => 'invitado@secreto.test']);

    $json = json_encode(toolCard(GetFirstSteps::class, $scope), JSON_THROW_ON_ERROR);

    expect($json)->not->toContain('Rosalinda')->not->toContain($employee->email)->not->toContain($invited->email)->not->toContain($employee->id);
});

it('refuses a scope of another company', function (): void {
    $scopeOfA = assistantScopeIn($this->a, TenantRole::Owner);
    workIn($this->b);

    expect(fn () => toolCard(GetFirstSteps::class, $scopeOfA))->toThrow(ScopeMismatch::class);
});

it('takes no argument', function (): void {
    expect(resolve(GetFirstSteps::class, ['scope' => assistantScopeIn($this->a, TenantRole::Owner)])->schema(new JsonSchemaTypeFactory))->toBe([]);
});

it('names the title of the evaluation step after its tool', function (string $module, string $title): void {
    $scope = assistantScopeIn($this->a, TenantRole::Owner);
    resolve(App\Modules\Tenancy\Contracts\CompanyModules::class)->syncPlan($this->a->id, [$module], Carbon\CarbonImmutable::now());

    $titles = array_column(toolCard(GetFirstSteps::class, $scope)['rows'], 'title');

    expect($titles)->toContain($title);
})->with([
    ['nom035', 'Lanza tu primera evaluación NOM-035'],
    ['nom037', 'Dinos a qué centros les aplica NOM-037'],
    ['seating', 'Dinos a qué centros les aplica Ley Silla'],
    ['attendance', 'Configura el registro de jornada'],
]);
