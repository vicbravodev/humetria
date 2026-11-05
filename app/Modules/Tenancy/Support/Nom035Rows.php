<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Campaigns\Data\CampaignRecord;
use Illuminate\Support\Facades\Gate;

/**
 * What the NOM-035 route of a center asks for, one requirement per stage (board V2-Ruta-N035), as an inspector would ask for it
 * (V3-Inspeccion): in plain words, where it stands and its way to do it (or, met, to see it) for whoever may. What follows the
 * evaluation waits for one to close; a center of up to 15 people answers only the Guía I (num. 2 a), so sharing, the Programa
 * and the report do not apply to it. The channeling is only ever counted, and for whoever may not see the Guía I it reads as
 * done, as in the panel (criterio C-28).
 *
 * @phpstan-type Row array{numeral: string, title: string, detail: string|null, state: string, stage: int, href: string|null, action: string|null}
 */
final readonly class Nom035Rows
{
    private const string WAITS = 'Llega al cerrar la evaluación';

    private const string SMALL = 'No aplica: hasta 15 personas solo contestan la Guía I';

    /**
     * @param  CampaignRecord|null  $evaluated  the latest evaluation closed within the cycle
     * @param  string|null  $channelAt  the evaluation whose people wait to be channeled, when one has
     * @return list<Row>
     */
    public function for(string $tenantSlug, Nom035Facts $facts, ?CampaignRecord $evaluated, ?string $channelAt): array
    {
        $evaluation = $evaluated?->id;

        return [
            $this->row(1, '5.1', 'Política de prevención por escrito', $facts->policy ? null : 'Aún no hay una política vigente en este centro', 'manage-disclosure', route('tenant.disclosure.policy', $tenantSlug), 'Escribir la política'),
            $this->evaluation($tenantSlug, $facts, $evaluation),
            $this->after($evaluation, $this->channeling($tenantSlug, $facts, $evaluation, $channelAt)),
            $this->chapterSeven($facts, $evaluation, $this->row(4, '7.8', 'Resultados a disposición de las personas', $facts->shared ? null : 'Aún no compartes los resultados de la última evaluación', 'manage-disclosure', route('tenant.disclosure.results', $tenantSlug), 'Preparar el resumen')),
            $this->chapterSeven($facts, $evaluation, $this->program($tenantSlug, $facts, $evaluation)),
            $this->chapterSeven($facts, $evaluation, $this->row(6, '7.7', 'Informe de resultados', $facts->reported ? null : 'Le faltan conclusiones, recomendaciones y responsable', 'manage-campaigns', $evaluation === null ? null : route('tenant.campaigns.report', [$tenantSlug, $evaluation]), 'Completar el informe')),
        ];
    }

    /**
     * What follows the evaluation waits for one to close.
     *
     * @param  Row  $row
     * @return Row
     */
    private function after(?string $evaluation, array $row): array
    {
        return $evaluation === null ? [...$row, 'state' => 'missing', 'detail' => self::WAITS, 'href' => null, 'action' => null] : $row;
    }

    /**
     * Chapter 7 (sharing, the Programa and the report) does not apply to a center of up to 15 people (num. 2 a).
     *
     * @param  Row  $row
     * @return Row
     */
    private function chapterSeven(Nom035Facts $facts, ?string $evaluation, array $row): array
    {
        return $facts->onlyGuideOne ? [...$row, 'state' => 'not_applicable', 'detail' => self::SMALL, 'href' => null, 'action' => null] : $this->after($evaluation, $row);
    }

    /**
     * Stage 3: who the Guía I says needs a clinical assessment and is not channeled yet (num. 5.5), counted. Whoever may not
     * see the Guía I is not told, not even counted (criterio C-28): the stage reads as done for them, and says why.
     *
     * @return Row
     */
    private function channeling(string $tenantSlug, Nom035Facts $facts, ?string $evaluation, ?string $channelAt): array
    {
        $title = 'Quien lo requiere según la Guía I, canalizado';

        if (! Gate::allows('view-trauma-screenings')) {
            return ['numeral' => '5.5 y 5.8 c', 'title' => $title, 'detail' => 'Solo lo ve quien puede ver la Guía I', 'state' => 'done', 'stage' => 3, 'href' => null, 'action' => null];
        }

        return $this->row(3, '5.5 y 5.8 c', $title, $facts->toChannel === 0 ? null : self::people($facts->toChannel), 'view-trauma-screenings', $evaluation === null ? null : route('tenant.campaigns.screenings', [$tenantSlug, $channelAt ?? $evaluation]), 'Canalizar');
    }

    /**
     * Stage 2: the evaluation within its two-year cycle (num. 7.9), the one that runs, or the way to start it.
     *
     * @return Row
     */
    private function evaluation(string $tenantSlug, Nom035Facts $facts, ?string $evaluation): array
    {
        $title = 'Evaluación de los factores de riesgo, vigente';

        if ($facts->standing === 'up_to_date' && $evaluation !== null) {
            return $this->row(2, '7.1 y 7.9', $title, null, 'manage-campaigns', route('tenant.campaigns.results', [$tenantSlug, $evaluation]), 'Ver resultados');
        }

        $detail = match (true) {
            $facts->running !== null => "{$facts->running['answered']} de {$facts->running['target']} contestaron; la evaluación sigue abierta",
            $facts->standing === 'overdue' => "Venció el {$facts->until}: toca evaluar de nuevo",
            default => 'El centro no se ha evaluado',
        };
        $row = $this->row(2, '7.1 y 7.9', $title, $detail, 'manage-campaigns', route('tenant.campaigns.index', $tenantSlug), $facts->running === null ? 'Evaluar' : 'Ver la evaluación');

        return $facts->running === null ? $row : [...$row, 'state' => 'partial'];
    }

    /**
     * Stage 5: the Programa of the latest evaluation (num. 8.3 to 8.5), where it stands; none was drafted when nothing reached
     * Medio, so the norm asks for none.
     *
     * @return Row
     */
    private function program(string $tenantSlug, Nom035Facts $facts, ?string $evaluation): array
    {
        $title = 'Programa de intervención con acciones, responsables y fechas';

        if ($evaluation !== null && $facts->program === null) {
            return ['numeral' => '8.3 a 8.5', 'title' => $title, 'detail' => 'Tus resultados no piden un programa', 'state' => 'not_applicable', 'stage' => 5, 'href' => null, 'action' => null];
        }

        $detail = match ($facts->program) {
            'done' => null,
            'in_progress' => 'Con acciones en curso',
            'overdue' => 'Con acciones vencidas',
            default => 'Aún no tiene acciones',
        };
        $row = $this->row(5, '8.3 a 8.5', $title, $detail, 'manage-interventions', $evaluation === null ? null : route('tenant.campaigns.program', [$tenantSlug, $evaluation]), $facts->program === 'none' ? 'Armar el programa' : 'Seguir el programa');

        return in_array($facts->program, ['in_progress', 'overdue'], true) ? [...$row, 'state' => 'partial'] : $row;
    }

    /**
     * A requirement: met without a detail, missing with what is behind it; its way in for whoever holds the permission.
     *
     * @return Row
     */
    private function row(int $stage, string $numeral, string $title, ?string $detail, string $permission, ?string $href, string $action): array
    {
        $may = $href !== null && Gate::allows($permission);

        return [
            'numeral' => $numeral,
            'title' => $title,
            'detail' => $detail,
            'state' => $detail === null ? 'done' : 'missing',
            'stage' => $stage,
            'href' => $may ? $href : null,
            'action' => $may ? ($detail === null && ! str_starts_with($action, 'Ver') ? 'Ver' : $action) : null,
        ];
    }

    private static function people(int $count): string
    {
        return $count === 1 ? '1 persona espera valoración clínica' : "{$count} personas esperan valoración clínica";
    }
}
