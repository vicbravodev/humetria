<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Data\RegulationParts;
use App\Modules\Seating\Enums\SeatCondition;
use App\Modules\Seating\Enums\SeatUse;
use App\Modules\Seating\Models\PauseAgreement;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\StandingPosition;

/**
 * Drafts the parts of the internal regulation from the data of a work center: its positions and their risk factors, the
 * pauses agreed with the people and where the seats are (criterio C-13). Where the data is not there yet it says what to
 * register, so no part is ever empty. The usage rule is a model clause, editable like the rest.
 */
final readonly class RegulationDraft
{
    private const string USAGE_RULE = 'El asiento o silla con respaldo está a disposición de todas las personas trabajadoras que lo necesiten. Nadie puede impedir que se use ni sancionar a quien lo use para descansar en sus pausas. Se usa con cuidado, se devuelve a su lugar y se avisa a la Comisión de Seguridad e Higiene si está dañado.';

    public function for(string $workCenterId): RegulationParts
    {
        $positions = array_values(StandingPosition::query()->where('work_center_id', $workCenterId)->orderBy('name')->orderBy('id')->get()->all());

        return new RegulationParts($this->riskFactors($positions), $this->pauses($positions), $this->seatLocation($workCenterId, $positions), self::USAGE_RULE);
    }

    /**
     * @param  list<StandingPosition>  $positions
     */
    private function riskFactors(array $positions): string
    {
        if ($positions === []) {
            return 'Registra los puestos donde se trabaja de pie para que aparezcan aquí con sus factores de riesgo.';
        }

        $lines = array_map(fn (StandingPosition $position): string => "- {$position->name}: bipedestación ".mb_strtolower($position->bipedestation_type->label()).", {$position->continuous_minutes} minutos continuos de pie.", $positions);

        return "Los factores de riesgo de trabajar de pie son el tiempo de pie, la postura, la movilidad, los periodos de descanso, la superficie y el puesto de trabajo. En este centro de trabajo se trabaja de pie en:\n".implode("\n", $lines);
    }

    /**
     * @param  list<StandingPosition>  $positions
     */
    private function pauses(array $positions): string
    {
        if ($positions === []) {
            return 'Registra los acuerdos de pausas de cada puesto para que aparezcan aquí.';
        }

        $lines = [];

        foreach ($positions as $position) {
            $agreement = PauseAgreement::query()->where('position_id', $position->id)->orderByDesc('agreed_on')->orderByDesc('id')->first();
            $lines[] = $agreement === null
                ? "- {$position->name}: por acordar."
                : "- {$position->name}: {$agreement->pauses_per_shift} ".($agreement->pauses_per_shift === 1 ? 'pausa' : 'pausas')." por turno de {$agreement->minutes_each} ".($agreement->minutes_each === 1 ? 'minuto' : 'minutos').'.';
        }

        return "Las personas que trabajan de pie tienen las siguientes pausas, acordadas con ellas:\n".implode("\n", $lines);
    }

    /**
     * @param  list<StandingPosition>  $positions
     */
    private function seatLocation(string $workCenterId, array $positions): string
    {
        $seats = Seat::query()->where('work_center_id', $workCenterId)->where('condition', '!=', SeatCondition::OutOfService)->get();

        if ($seats->isEmpty()) {
            return 'Registra los asientos del centro para que aparezcan aquí con su ubicación.';
        }

        $areas = $seats->where('use', SeatUse::RestArea)->map(fn (Seat $seat): string => (string) $seat->location)->unique()->values()->all();
        $lines = [];

        foreach ($positions as $position) {
            $count = $seats->where('position_id', $position->id)->reduce(fn (int $carry, Seat $seat): int => $carry + $seat->quantity, 0);
            $lines[] = $count > 0 ? "- {$position->name}: {$count} ".($count === 1 ? 'asiento' : 'asientos').' en el puesto.' : "- {$position->name}: sin asientos registrados.";
        }

        return implode("\n", array_filter([
            $areas === [] ? null : 'Áreas designadas para los descansos, en las mismas instalaciones: '.implode('; ', $areas).'.',
            $lines === [] ? null : "Asientos por puesto:\n".implode("\n", $lines),
        ]));
    }
}
