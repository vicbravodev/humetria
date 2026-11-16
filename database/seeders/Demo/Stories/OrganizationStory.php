<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Stories;

use App\Models\User;
use App\Modules\Organization\Actions\CreateEmployee;
use App\Modules\Organization\Data\EmployeeData;
use App\Modules\Organization\Enums\Gender;
use App\Modules\Organization\Enums\StaffGroup;
use App\Modules\Tenancy\Actions\AcceptInvitation;
use App\Modules\Tenancy\Actions\ActivateTenant;
use App\Modules\Tenancy\Actions\CreateTenant;
use App\Modules\Tenancy\Actions\CreateWorkCenter;
use App\Modules\Tenancy\Actions\InviteMember;
use App\Modules\Tenancy\Actions\InviteOwner;
use App\Modules\Tenancy\Data\MemberInvitationData;
use App\Modules\Tenancy\Data\OwnerInvitationData;
use App\Modules\Tenancy\Data\TenantData;
use App\Modules\Tenancy\Data\WorkCenterData;
use App\Modules\Tenancy\Enums\MexicanState;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Database\Seeders\Demo\DemoStory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manufacturas Alba del Bajío: the company, its four work centers and its 180 people with their areas, job grades and
 * reporting lines, and the two panel members (the director as owner, the head of HR). With no plan it has every module
 * and no limit on seats or centers.
 */
final readonly class OrganizationStory implements DemoStory
{
    /** Fictitious: the 31st of February keeps the SAT from ever validating it. */
    public const string RFC = 'MAB990231AB1';

    public const string SLUG = 'demo-alba-del-bajio';

    public function __construct(
        private CreateTenant $createTenant,
        private ActivateTenant $activateTenant,
        private InviteOwner $inviteOwner,
        private InviteMember $inviteMember,
        private AcceptInvitation $acceptInvitation,
        private CreateWorkCenter $createWorkCenter,
        private CreateEmployee $createEmployee,
    ) {}

    /**
     * The demo company, kept across resets; the first time it is created flagged as the demo from its insert (the flag never
     * changes afterwards) and active for years, in one transaction: it never bills nor expires.
     */
    public function company(): Tenant
    {
        $existing = Tenant::query()->where('is_demo', true)->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(fn (): Tenant => $this->activateTenant->handle(
            $this->createTenant->handle(new TenantData('Manufacturas Alba del Bajío, S.A. de C.V.', 'Alba del Bajío', self::RFC, self::SLUG), demo: true),
            CarbonImmutable::now()->addYears(10),
        ));
    }

    /** The owner and the HR member, through the invitations a real company goes through; call it inside the company. */
    public function members(Tenant $tenant): DemoContext
    {
        $owner = $this->user(DemoRoster::person(DemoRoster::OWNER));
        $hr = $this->user(DemoRoster::person(DemoRoster::HR));

        $this->acceptInvitation->handle($this->inviteOwner->handle($tenant, new OwnerInvitationData($owner->name, $owner->email), $owner), $owner);
        $this->acceptInvitation->handle($this->inviteMember->handle($tenant, new MemberInvitationData($hr->name, $hr->email, TenantRole::Hr), $owner), $hr);

        return new DemoContext($tenant->id, $owner, $hr);
    }

    public function tell(DemoContext $context): void
    {
        foreach ($this->centers() as $center) {
            $this->createWorkCenter->handle($center);
        }

        $today = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay();
        // The door's people get new ids on every prepare: a portal link of an earlier audience finds nobody.
        DemoRoster::drawDoorIds();

        foreach (DemoRoster::people() as $person) {
            $this->createEmployee->handle($this->employee($person, $context->center($person->centerKey), $today));
        }
    }

    private function employee(DemoPerson $person, string $centerId, CarbonImmutable $today): EmployeeData
    {
        return new EmployeeData(
            name: $person->name,
            email: $person->email,
            mobile: null,
            gender: Gender::from($person->gender),
            workCenterId: $centerId,
            area: $person->area,
            jobTitle: $person->position,
            employeeNumber: $person->employeeNumber,
            jobCategory: $person->category,
            jobLevel: $person->level,
            hiredOn: $today->subDays($person->tenureDays),
            // The rank and file are under the collective contract; everyone else is trusted staff (Compensación costs both).
            staffGroup: $person->category === 'Operativo' ? StaffGroup::Unionized : StaffGroup::Trusted,
            state: null,
            managerId: $person->reportsTo === null ? null : DemoRoster::employeeId($person->reportsTo),
            id: DemoRoster::employeeId($person->key),
        );
    }

    /**
     * A panel account for a person of the roster: verified, with a password nobody knows (the console enters by
     * impersonation). A reset keeps it (users belong to no company), so preparing again finds it.
     */
    private function user(DemoPerson $person): User
    {
        return User::query()->where('email', $person->email)->first() ?? User::query()->forceCreate([
            'name' => $person->name,
            'email' => $person->email,
            'password' => Str::random(64),
            'email_verified_at' => now(),
        ]);
    }

    /** @return list<WorkCenterData> */
    private function centers(): array
    {
        return [
            new WorkCenterData(DemoRoster::CENTERS['plant'], 'Avenida de la Industria', '1450', null, 'Parque Industrial Benito Juárez', '76120', 'Querétaro', MexicanState::Queretaro, 'Manufactura de autopartes metálicas'),
            new WorkCenterData(DemoRoster::CENTERS['distribution'], 'Carretera Estatal 431', 'km 2.5', 'Nave 3', 'Parque Industrial El Marqués', '76246', 'El Marqués', MexicanState::Queretaro, 'Almacenamiento y distribución de autopartes'),
            new WorkCenterData(DemoRoster::CENTERS['offices'], 'Boulevard Bernardo Quintana', '4100', 'Piso 6', 'Álamos 3a Sección', '76160', 'Querétaro', MexicanState::Queretaro, 'Servicios administrativos y comerciales'),
            new WorkCenterData(DemoRoster::CENTERS['monterrey'], 'Avenida Ruiz Cortines', '2300', null, 'Industrial', '64440', 'Monterrey', MexicanState::NuevoLeon, 'Venta de autopartes al mayoreo'),
        ];
    }
}
