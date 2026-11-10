<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Contracts\PortalAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Mi equipo» in a person's portal, under the same link as the rest: their manager, whoever reports to the same
 * person and whoever reports to them. Name, job title and area only: no contact data and nobody's id. A link that is
 * not valid is a 404 that says nothing about whether it ever existed.
 */
final class PortalTeamController extends Controller
{
    public const int PER_PAGE = 10;

    public function show(string $token, PortalAccess $access): Response
    {
        $identity = $access->open($token);
        abort_if($identity === null, 404);

        $person = Employee::query()->findOrFail($identity->employeeId, ['id', 'manager_id']);
        $manager = $person->manager_id === null ? null : Employee::query()->whereNull('deactivated_at')->find($person->manager_id, ['id', 'name', 'employee_number', 'job_title']);

        return Inertia::render('organization/portal-team', [
            'token' => $token,
            'company' => $identity->company,
            'name' => $identity->name,
            // A manager who left is not named: the person reads they have none and tells Recursos Humanos.
            'manager' => $manager === null ? null : ['name' => $manager->name ?? $manager->employee_number ?? '', 'job_title' => $manager->job_title ?? ''],
            // With no manager nobody is beside the person: comparing with NULL matches no one.
            'peers' => $this->people(Employee::query()->whereRaw('manager_id = ?', [$person->manager_id])->whereKeyNot($person->id), 'peers'),
            'reports' => $this->people(Employee::query()->where('manager_id', $person->id), 'reports'),
        ]);
    }

    /**
     * The active people of a query by name, a few at a time, each list turning its own pages.
     *
     * @param  Builder<Employee>  $query
     * @return LengthAwarePaginator<int, array{name: string, job_title: string, area: string}>
     */
    private function people(Builder $query, string $pageName): LengthAwarePaginator
    {
        return $query
            ->whereNull('deactivated_at')
            ->with('area:id,name')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE, ['id', 'name', 'employee_number', 'job_title', 'area_id'], $pageName)
            ->withQueryString()
            ->through(fn (Employee $employee): array => [
                'name' => $employee->name ?? $employee->employee_number ?? '',
                'job_title' => $employee->job_title ?? '',
                'area' => $employee->area->name ?? '',
            ]);
    }
}
