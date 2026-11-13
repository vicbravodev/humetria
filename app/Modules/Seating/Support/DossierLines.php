<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Instruments\Data\SeatPlacement;
use App\Modules\Seating\Enums\DocumentKind;
use App\Modules\Seating\Enums\RegulationStatus;
use App\Modules\Seating\Enums\SeatUse;
use App\Modules\Seating\Models\PauseAgreement;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\SeatingDocument;
use App\Modules\Seating\Models\StandingPosition;
use Carbon\CarbonInterface;

/**
 * The lines of each section of the dossier: what exists, each with its date when it has one. A section with nothing says
 * «Falta» when it is missing and «Sin pendientes» when the requirement is met without records. Counts and states only:
 * nothing here names a person or carries health data.
 */
final class DossierLines
{
    private const array LETTERS = ['at_workstation' => 'A', 'nearby' => 'B', 'within_center' => 'C'];

    /**
     * @return list<array{text: string, date: string|null}>
     */
    public static function of(string $key, string $state, int $count, DossierFacts $facts): array
    {
        $lines = match ($key) {
            'r1' => self::sector($facts),
            'r2' => self::people($state, $count, $facts),
            'r3' => self::minutes($facts),
            'r4' => self::seats($facts, fn (Seat $seat): bool => $seat->position_id !== null),
            'r5' => self::seats($facts, fn (Seat $seat): bool => $seat->use === SeatUse::RestArea),
            'r6' => self::seats($facts, fn (Seat $seat): bool => $seat->use === SeatUse::RestArea || $seat->position_id === null),
            'r7' => self::measures($facts),
            'r11' => self::agreements($facts),
            'r10' => self::regulation($facts),
            'r12' => self::diagnosis($facts),
            'r13' => $facts->committeeMembers > 0 ? [self::line("{$facts->committeeMembers} ".($facts->committeeMembers === 1 ? 'integrante' : 'integrantes').' de la Comisión')] : [],
            default => [],
        };

        return $lines !== [] ? $lines : [self::line(match ($state) {
            'met' => 'Sin pendientes',
            'not_applicable' => 'No aplica: ningún puesto con resultado B o C',
            'pending' => 'Sin registros',
            default => 'Falta',
        })];
    }

    /**
     * @return array{text: string, date: string|null}
     */
    private static function line(string $text, ?CarbonInterface $date = null): array
    {
        return ['text' => $text, 'date' => $date?->toDateString()];
    }

    /**
     * @return list<array{text: string, date: string|null}>
     */
    private static function sector(DossierFacts $facts): array
    {
        $lines = [];

        if ($facts->profile !== null) {
            $facts->profile->sector !== null && $lines[] = self::line("Sector declarado: {$facts->profile->sector->label()}", $facts->profile->declared_on);
            $facts->profile->no_standing_positions && $lines[] = self::line('Declara que no tiene puestos en bipedestación');
        }

        foreach ($facts->positions as $position) {
            $lines[] = self::line(self::positionResult($position));
        }

        return $lines;
    }

    private static function positionResult(StandingPosition $position): string
    {
        return $position->placement instanceof SeatPlacement
            ? "{$position->name}: resultado ".self::LETTERS[$position->placement->value]
            : "{$position->name}: la naturaleza del trabajo no permite sentarse".($position->nature_reason === null ? '' : " ({$position->nature_reason})");
    }

    /**
     * @return list<array{text: string, date: string|null}>
     */
    private static function people(string $state, int $count, DossierFacts $facts): array
    {
        $people = $facts->signed->pluck('employee_id')->unique()->count();
        $lines = $people === 0 ? [] : [self::line("{$people} ".($people === 1 ? 'persona con evaluación firmada' : 'personas con evaluación firmada'), $facts->signed->last()?->signed_at)];
        $state !== 'met' && $lines[] = self::line(($state === 'missing' ? 'Sin evaluación vigente: ' : 'Por reevaluar: ').$count);

        return $lines;
    }

    /**
     * @return list<array{text: string, date: string|null}>
     */
    private static function minutes(DossierFacts $facts): array
    {
        $total = $facts->signed->count();
        $recorded = $facts->signed->filter(fn (RiskAssessment $assessment): bool => $assessment->recorded_in_document_id !== null)->count();

        return $total === 0 ? [] : [self::line("{$recorded} de {$total} ".($total === 1 ? 'evaluación firmada' : 'evaluaciones firmadas').' con su acta')];
    }

    /**
     * @param  callable(Seat): bool  $matches
     * @return list<array{text: string, date: string|null}>
     */
    private static function seats(DossierFacts $facts, callable $matches): array
    {
        return array_values($facts->seats->filter($matches)->map(fn (Seat $seat): array => self::line(sprintf(
            '%s × %d: %s, %s%s%s',
            $seat->kind->label(),
            $seat->quantity,
            mb_strtolower($seat->use->label()),
            mb_strtolower($seat->condition->label()),
            $seat->use === SeatUse::RestArea || $seat->position_id === null ? ($seat->signposted ? ', señalizado' : ', sin señalizar') : '',
            $seat->location === null ? '' : " ({$seat->location})",
        )))->all());
    }

    /**
     * @return list<array{text: string, date: string|null}>
     */
    private static function measures(DossierFacts $facts): array
    {
        return array_values($facts->measures->map(fn (PreventiveMeasure $measure): array => self::line("{$measure->kind->label()}: {$measure->status->label()}", $measure->due_on))->all());
    }

    /**
     * @return list<array{text: string, date: string|null}>
     */
    private static function agreements(DossierFacts $facts): array
    {
        $names = [];

        foreach ($facts->positions as $position) {
            $names[$position->id] = $position->name;
        }

        return array_values($facts->agreements->map(fn (PauseAgreement $agreement): array => self::line(sprintf(
            '%s: %d %s de %d minutos por turno',
            $names[$agreement->position_id] ?? 'Puesto',
            $agreement->pauses_per_shift,
            $agreement->pauses_per_shift === 1 ? 'pausa' : 'pausas',
            $agreement->minutes_each,
        ), $agreement->agreed_on))->all());
    }

    /**
     * @return list<array{text: string, date: string|null}>
     */
    private static function regulation(DossierFacts $facts): array
    {
        $regulation = $facts->regulation;

        if ($regulation === null) {
            return [];
        }

        $lines = [self::line('Estado: '.ucfirst(match ($regulation->status) {
            RegulationStatus::Draft => 'borrador',
            RegulationStatus::Signed => 'firmado',
            RegulationStatus::Deposited => 'depositado',
            RegulationStatus::Distributed => 'repartido y publicado',
        }))];
        $regulation->signed_on !== null && $lines[] = self::line('Firma de la comisión mixta', $regulation->signed_on);
        $regulation->deposited_on !== null && $lines[] = self::line('Depósito ante la autoridad', $regulation->deposited_on);
        $regulation->deposit_folio !== null && $lines[] = self::line("Folio del depósito: {$regulation->deposit_folio}");
        $regulation->distributed_on !== null && $lines[] = self::line('Reparto y publicación', $regulation->distributed_on);

        return $lines;
    }

    /**
     * @return list<array{text: string, date: string|null}>
     */
    private static function diagnosis(DossierFacts $facts): array
    {
        return array_values($facts->documents->filter(fn (SeatingDocument $document): bool => $document->kind === DocumentKind::DiagnosisProgram)
            ->map(fn (SeatingDocument $document): array => self::line($document->title.($document->incisos === [] ? '' : ' (incisos '.implode(', ', $document->incisos).')'), $document->document_on))->all());
    }
}
