<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Tenancy\Contracts\Companies;
use Database\Factories\Campaigns\Nom035EvaluationScenario;
use Database\Factories\Interventions\ProgramScenario;
use Database\Factories\Organization\AreaFactory;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\TenantMembershipFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Bus;

/**
 * A company that exercises NOM-035 end to end, to judge the instruments against the DOF and the screens against use.
 * The campaigns go through the real actions (draft, launch, answer by link, close), so what you see is what a company gets.
 *
 * | Work center | People | Guides            | State                                                      |
 * |-------------|--------|-------------------|------------------------------------------------------------|
 * | 15          | 15     | I                 | active, 9 answered: the largest center with only Guía I    |
 * | 16          | 16     | I, II             | draft: the smallest center that takes Guía II              |
 * | 30          | 30     | I, II             | closed, all answered: a person on every limit of II.3 c    |
 * | 51          | 51     | I, III (everyone) | active, half answered: the smallest center that takes III  |
 * | 100         | 100    | I, III (sample)   | closed: n = 80 (III.1), a person on every limit of III.3 c |
 *
 * The two closed ones also have their Programa de intervención: a draft at the 30-person center, and at the 100-person one a
 * Programa under way (done, running, overdue, planned and cancelled actions, with steps and an analysis).
 *
 * It sends no email (the invitation job is faked), so it can run anywhere; the links of the active campaigns are printed.
 * Run it on a fresh database: `sail artisan migrate:fresh --seed --seeder=Nom035EvaluationSeeder`.
 */
final class Nom035EvaluationSeeder extends Seeder
{
    public const string SLUG = 'demo-nom035';

    public const string OWNER_EMAIL = 'owner@demo-nom035.test';

    public const array MEMBER_EMAILS = [self::OWNER_EMAIL, 'hr@demo-nom035.test', 'viewer@demo-nom035.test'];

    public const string CENTER_15 = 'Taller (15 personas)';

    public const string CENTER_16 = 'Bodega (16 personas)';

    public const string CENTER_30 = 'Oficina (30 personas)';

    public const string CENTER_51 = 'Sucursal (51 personas)';

    public const string CENTER_100 = 'Planta (100 personas)';

    /**
     * The "yes" answers of the Guía I scenarios, with a person on each side of every GR.I threshold: an event with
     * nothing after it, one in section II, two and three in section III, one and two in section IV.
     */
    public const array GUIDE_ONE_YES = [[], [3], [3, 7], [3, 9, 10], [3, 9, 10, 11], [3, 16], [3, 16, 17]];

    /**
     * At the 51-person center Guía III is answered by the first 26 people and Guía I by 25 from the 22nd on: 5 did
     * both, 21 only III, 20 only I and 5 none.
     */
    public const int ANSWERED_AT_CENTER_51 = 26;

    /**
     * One final score per person of the 30-person center. Every limit of Tabla II.3 c (19/20, 44/45, 69/70, 89/90)
     * has a person on each side, and each of the five levels has people: 6, 6, 6, 5 and 7.
     *
     * @return list<int>
     */
    public static function guideTwoTotals(): array
    {
        return [19, 20, 44, 45, 69, 70, 89, 90, 0, 5, 10, 14, 17, 22, 28, 33, 38, 50, 55, 60, 65, 75, 80, 85, 95, 110, 130, 150, 170, 184];
    }

    /**
     * One final score per sampled person of the 100-person center. Every limit of Tabla III.3 c (49/50, 74/75, 98/99,
     * 139/140) has a person on each side, and the rest are spread evenly inside each level.
     *
     * @return list<int>
     */
    public static function guideThreeTotals(): array
    {
        return [
            49, 50, 74, 75, 98, 99, 139, 140,
            ...self::spread(0, 40, 12),
            ...self::spread(52, 72, 14),
            ...self::spread(78, 96, 16),
            ...self::spread(102, 136, 18),
            ...self::spread(145, 250, 12),
        ];
    }

    public function run(): void
    {
        if (User::query()->where('email', self::OWNER_EMAIL)->exists()) {
            $this->command?->warn('The demo company already exists; run it on a fresh database (migrate:fresh).');

            return;
        }

        // The invitations are queued after commit: faked, no email leaves, and the scenario marks the links as sent.
        Bus::fake();

        $tenant = TenantFactory::new()->activeUntil(now()->addYears(5))->createOne([
            'legal_name' => 'Demo NOM-035 S.A. de C.V.',
            'trade_name' => 'Demo NOM-035',
            'rfc' => 'DNO261102AB1',
            'slug' => self::SLUG,
        ]);
        $owner = $this->members($tenant);
        resolve(Companies::class)->enter($tenant->id);
        $areas = [];
        $centers = [];

        foreach ($this->plans() as $plan) {
            $center = WorkCenterFactory::new()->createOne(['tenant_id' => $tenant->id, 'name' => $plan['name'], 'main_activity' => $plan['activity']]);
            $centers[$plan['name']] = $center->id;
            $this->staff($tenant->id, $center->id, $plan['areas'], $areas);
            $totals = $plan['name'] === self::CENTER_30 ? self::guideTwoTotals() : self::guideThreeTotals();
            $links = (new Nom035EvaluationScenario($totals, self::GUIDE_ONE_YES))
                ->run($center->id, $plan['sample'], $plan['state'], $owner->id, $plan['guideOneStart'], $plan['guideOne'], $plan['risk']);

            if ($links !== []) {
                $this->command?->info("Enlaces abiertos de «{$plan['name']}»:");

                foreach ($links as $link) {
                    $this->command?->line("  {$link}");
                }
            }
        }

        // The Programa de intervención of the two closed evaluations: one only drafted, the other well on its way.
        $programs = new ProgramScenario;
        $programs->draft($centers[self::CENTER_30], $owner->id);
        $programs->run($centers[self::CENTER_100], $owner->id);
    }

    /**
     * @return list<array{name: string, activity: string, areas: array<string, int>, sample: bool, state: 'draft'|'active'|'closed', guideOne: int, guideOneStart: int, risk: int}>
     */
    private function plans(): array
    {
        return [
            ['name' => self::CENTER_15, 'activity' => 'Reparación de maquinaria industrial', 'areas' => ['Producción' => 8, 'Almacén' => 4, 'Administración' => 3], 'sample' => false, 'state' => 'active', 'guideOne' => 9, 'guideOneStart' => 0, 'risk' => 0],
            ['name' => self::CENTER_16, 'activity' => 'Almacenamiento y distribución', 'areas' => ['Almacén' => 10, 'Administración' => 6], 'sample' => false, 'state' => 'draft', 'guideOne' => 0, 'guideOneStart' => 0, 'risk' => 0],
            ['name' => self::CENTER_30, 'activity' => 'Servicios administrativos y comerciales', 'areas' => ['Ventas' => 12, 'Operaciones' => 10, 'Finanzas' => 5, 'Dirección' => 3], 'sample' => false, 'state' => 'closed', 'guideOne' => 30, 'guideOneStart' => 0, 'risk' => 30],
            ['name' => self::CENTER_51, 'activity' => 'Venta de refacciones al mayoreo', 'areas' => ['Ventas' => 20, 'Operaciones' => 18, 'Administración' => 13], 'sample' => false, 'state' => 'active', 'guideOne' => 25, 'guideOneStart' => 21, 'risk' => self::ANSWERED_AT_CENTER_51],
            ['name' => self::CENTER_100, 'activity' => 'Manufactura de autopartes', 'areas' => ['Producción' => 40, 'Almacén' => 25, 'Calidad' => 20, 'Mantenimiento' => 15], 'sample' => true, 'state' => 'closed', 'guideOne' => 92, 'guideOneStart' => 0, 'risk' => 80],
        ];
    }

    /**
     * @param  array<string, int>  $people  area name => people
     * @param  array<string, string>  $areas  area name => id, filled as areas are created: a company has each area once
     */
    private function staff(string $tenantId, string $centerId, array $people, array &$areas): void
    {
        $person = 0;

        foreach ($people as $name => $count) {
            $areas[$name] ??= AreaFactory::new()->createOne(['tenant_id' => $tenantId, 'name' => $name])->id;

            for ($i = 0; $i < $count; $i++) {
                // About 45% women, 50% men and 5% "otro / prefiero no decir", so the sample is split by gender.
                $gender = match (true) {
                    $person % 20 < 9 => 'female',
                    $person % 20 < 19 => 'male',
                    default => 'other',
                };
                EmployeeFactory::new()->createOne(['tenant_id' => $tenantId, 'work_center_id' => $centerId, 'area_id' => $areas[$name], 'gender' => $gender]);
                $person++;
            }
        }
    }

    /** The company's owner, an HR user and a viewer. */
    private function members(Model $tenant): User
    {
        $owner = User::factory()->create(['email' => self::MEMBER_EMAILS[0], 'name' => 'Ana Dueña']);
        $hr = User::factory()->create(['email' => self::MEMBER_EMAILS[1], 'name' => 'Hugo RH']);
        $viewer = User::factory()->create(['email' => self::MEMBER_EMAILS[2], 'name' => 'Vera Consulta']);

        TenantMembershipFactory::new()->for($tenant)->for($owner)->createOne();
        TenantMembershipFactory::new()->for($tenant)->for($hr)->hr()->createOne();
        TenantMembershipFactory::new()->for($tenant)->for($viewer)->viewer()->createOne();

        return $owner;
    }

    /**
     * @return list<int>
     */
    private static function spread(int $from, int $to, int $count): array
    {
        return array_map(fn (int $i): int => $from + intdiv(($to - $from) * $i, $count - 1), range(0, $count - 1));
    }
}
