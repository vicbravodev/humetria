<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Stories;

use App\Modules\Campaigns\Actions\SubmitSurvey;
use App\Modules\Campaigns\Contracts\CenterSurveys;
use App\Modules\Campaigns\Enums\InvitationStatus;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Instruments\Data\SeatPlacement;
use App\Modules\Seating\Actions\AcknowledgeRisks;
use App\Modules\Seating\Actions\AssignPeople;
use App\Modules\Seating\Actions\DeclareProfile;
use App\Modules\Seating\Actions\ProposeMeasure;
use App\Modules\Seating\Actions\SaveAssessmentDraft;
use App\Modules\Seating\Actions\SaveMeasure;
use App\Modules\Seating\Actions\SavePauseAgreement;
use App\Modules\Seating\Actions\SavePosition;
use App\Modules\Seating\Actions\SaveSeat;
use App\Modules\Seating\Actions\SignAssessment;
use App\Modules\Seating\Actions\StartAssessment;
use App\Modules\Seating\Data\MeasureData;
use App\Modules\Seating\Data\PositionData;
use App\Modules\Seating\Data\SeatData;
use App\Modules\Seating\Enums\BipedestationType;
use App\Modules\Seating\Enums\MeasureKind;
use App\Modules\Seating\Enums\MeasureStatus;
use App\Modules\Seating\Enums\SeatCondition;
use App\Modules\Seating\Enums\SeatFeature;
use App\Modules\Seating\Enums\SeatKind;
use App\Modules\Seating\Enums\SeatUse;
use App\Modules\Seating\Enums\Sector;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Actions\RegisterCommittee;
use App\Modules\Telework\Contracts\PortalAccess;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Database\Seeders\Demo\DemoStory;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Ley Silla at the offices. The Comisión de seguridad e higiene of the offices was constituted over a year ago, with the head
 * of HR on it (the Comisión is the company's: Telework keeps it and Ley Silla reads it). Four months ago HR declared the
 * offices a services center and registered its three standing positions (the sales counter, the on-site support and the
 * filing and messenger runs), their seats and a rest area, and agreed the pauses. The Comisión assessed every standing
 * person and signed all but the last, still a draft; the Medio and Alto ones have their measures, some done and some open.
 * Everyone confirmed their assessment from their portal but the people kept for the door. Six weeks ago HR ran the
 * confidential survey of the center through its link; everyone but the door answered it, and it closed two weeks later.
 */
final readonly class SeatingStory implements DemoStory
{
    /** Months before Ley Silla, the Comisión of the offices was constituted for telework. */
    public const int COMMITTEE_DAYS_AGO = 430;

    private const int REGISTERED_DAYS_AGO = 120;

    private const int ASSESSED_DAYS_AGO = 100;

    private const int SURVEY_DAYS_AGO = 45;

    private const array AREA = [
        'noise' => 'Conversaciones de oficina, por debajo de 60 dB.',
        'lighting' => 'Luz natural y luminarias LED de 500 lx.',
        'ventilation' => 'Aire acondicionado central.',
        'temperature' => 'Entre 21 y 24 °C todo el año.',
        'vibration' => 'Ninguna.',
        'chemicals' => 'Ninguna.',
    ];

    /**
     * The standing positions by the job title of the roster that holds them: name, tasks, type, minutes standing in a row,
     * the three answers of Figura 1 (can work seated, room for a seat, must be close by), people at once, tools, physical
     * load, attention, the five conditions the position gives (a, b, c, e, g) and the seat it has.
     *
     * @var array<string, array{string, string, BipedestationType, int, array{bool, bool, bool}, int, string, string, string, array{int, int, int, int, int}, SeatKind}>
     */
    private const array POSITIONS = [
        'Asistente comercial' => ['Mostrador de atención a clientes', 'Atiende a clientes y proveedores en el mostrador de la recepción comercial, recibe muestras y entrega pedidos.', BipedestationType::Static, 240, [false, true, true], 2, 'Computadora, terminal de cobro y teléfono sobre un mostrador de 1.05 m.', 'Ligera: cajas de muestras de hasta 5 kg.', 'Alto: atiende a varios clientes a la vez.', [3, 2, 2, 2, 2], SeatKind::HighChair],
        'Soporte técnico' => ['Soporte técnico en sitio', 'Instala y repara equipos en los escritorios de las oficinas y en la sala de juntas.', BipedestationType::Dynamic, 90, [true, true, false], 2, 'Herramienta de mano, laptop y equipo de cómputo de las oficinas.', 'Ligera: equipos de hasta 8 kg.', 'Medio: tareas técnicas con pausas entre servicios.', [1, 1, 1, 1, 1], SeatKind::ErgonomicChair],
        'Auxiliar contable' => ['Archivo y mensajería', 'Archiva expedientes en el cuarto de archivo y lleva documentos entre áreas y a los bancos cercanos.', BipedestationType::Combined, 150, [false, false, false], 3, 'Archiveros de cuatro gavetas y escalerilla.', 'Moderada: cajas de archivo de hasta 10 kg.', 'Medio: clasificación de documentos.', [2, 1, 2, 1, 1], SeatKind::HighStool],
    ];

    /**
     * The person's own conditions (d footwear, f discomfort), by the position and their place in it.
     *
     * @var array<string, list<array{int, int}>>
     */
    private const array PERSONAL = [
        'Asistente comercial' => [[2, 2], [1, 1]],
        'Soporte técnico' => [[1, 1], [1, 1]],
        'Auxiliar contable' => [[1, 1], [2, 1], [1, 1]],
    ];

    /**
     * The measures of the Medio and Alto assessments, by the position and the person's place in it: kind, the status it
     * reached, the day it reached it and the day it is due (both from today).
     *
     * @var array<string, array<int, list<array{MeasureKind, MeasureStatus, int, int}>>>
     */
    private const array MEASURES = [
        'Asistente comercial' => [
            [[MeasureKind::CushionedFloor, MeasureStatus::Done, -60, -55], [MeasureKind::ActiveBreaks, MeasureStatus::InProgress, -40, 20]],
            [[MeasureKind::WorkstationDesign, MeasureStatus::Done, -75, -70]],
        ],
        'Auxiliar contable' => [
            [[MeasureKind::TaskRotation, MeasureStatus::InProgress, -50, 15]],
            [[MeasureKind::ErgonomicFootwear, MeasureStatus::Planned, -94, -10]],
        ],
    ];

    public function __construct(
        private RegisterCommittee $committee,
        private DeclareProfile $profile,
        private SavePosition $savePosition,
        private AssignPeople $assign,
        private SaveSeat $saveSeat,
        private SavePauseAgreement $pauses,
        private StartAssessment $start,
        private SaveAssessmentDraft $draft,
        private SignAssessment $sign,
        private ProposeMeasure $propose,
        private SaveMeasure $saveMeasure,
        private PortalAccess $portal,
        private AcknowledgeRisks $acknowledge,
        private CenterSurveys $surveys,
        private SubmitSurvey $submit,
    ) {}

    public function tell(DemoContext $context): void
    {
        $hr = $context->hr->id;
        $offices = $context->center('offices');
        DemoClock::at('-'.self::COMMITTEE_DAYS_AGO.' days', fn () => $this->committee->handle($offices, CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay(), null, [$hr], $hr));

        $positions = DemoClock::at('-'.self::REGISTERED_DAYS_AGO.' days', fn (): array => $this->register($offices, $hr));
        $signed = 0;

        foreach ($positions as $role => $position) {
            foreach ($this->holders($role) as $i => $person) {
                $signed += $this->assess($offices, $hr, $role, $position, $person, $i) ? 1 : 0;
            }
        }

        $answered = $this->survey($offices, $hr);
        Log::info('demo.seating_seeded', ['assessments_signed' => $signed, 'survey_answered' => $answered]);
    }

    /**
     * HR declares the center, registers its positions with the people in them and their seats, a rest area for those whose
     * seat is not at the workstation, and the pauses agreed for them.
     *
     * @return array<string, StandingPosition> by the job title of the roster that holds them
     */
    private function register(string $offices, int $hr): array
    {
        $this->profile->handle($offices, Sector::Services, false, null, $hr);
        $positions = [];

        foreach (self::POSITIONS as $role => [$name, $tasks, $type, $minutes, $figure, $max, $tools, $load, $attention, $given, $seat]) {
            [$seated, $space, $nearby] = $figure;
            [$a, $b, $c, $e, $g] = $given;
            $position = $this->savePosition->handle(new PositionData(null, $offices, $name, $tasks, $type, $minutes, $seated, $space, $nearby, $max, self::AREA, $tools, $load, $attention, $a, $b, $c, $e, $g, null, null));
            $this->assign->handle($position, array_map(fn (DemoPerson $person): string => DemoRoster::employeeId($person->key), $this->holders($role)));
            $features = $seat === SeatKind::ErgonomicChair ? SeatFeature::cases() : [SeatFeature::Backrest, SeatFeature::Height, SeatFeature::Stability, SeatFeature::Footrest];
            $this->saveSeat->handle(new SeatData(null, $offices, $position->id, $name, $seat, SeatUse::Workstation, $max, $this->features($features), SeatCondition::Good, true), null);
            $positions[$role] = $position;

            if ($position->placement !== SeatPlacement::AtWorkstation) {
                $this->pauses->handle($position, 2, 10, CarbonImmutable::now(DemoClock::TIMEZONE)->toDateString(), 'Acordado con las personas del puesto en una reunión con la Comisión; queda en la minuta.', null, $hr);
            }
        }

        $this->saveSeat->handle(new SeatData(null, $offices, null, 'Área de descanso del piso 6, junto al comedor', SeatKind::ErgonomicChair, SeatUse::RestArea, 6, $this->features(SeatFeature::cases()), SeatCondition::Good, true), null);

        return $positions;
    }

    /**
     * The Comisión assesses a person: the position's conditions and the person's own, the facts of Quinta C, and signs; the
     * last person of the filing runs is still a draft. The measures of a Medio or Alto assessment follow, and the person
     * confirms it from their portal unless they are kept for the door.
     *
     * @return bool whether it was signed
     */
    private function assess(string $offices, int $hr, string $role, StandingPosition $position, DemoPerson $person, int $i): bool
    {
        $id = DemoRoster::employeeId($person->key);
        $last = $i === count(self::PERSONAL[$role]) - 1 && $role === 'Auxiliar contable';
        $day = $last ? -4 : -self::ASSESSED_DAYS_AGO + $i;
        [$footwear, $discomfort] = self::PERSONAL[$role][$i];

        $assessment = DemoClock::at("{$day} days", function () use ($offices, $hr, $id, $position, $footwear, $discomfort, $last): RiskAssessment {
            $assessment = $this->draft->handle($this->start->handle($offices, $id, $hr), ['footwear' => $footwear, 'discomfort' => $discomfort], [
                'other_aspects' => "Recorrido de la Comisión por {$position->name}: sin otros aspectos que agregar.",
                'verified_on' => CarbonImmutable::now(DemoClock::TIMEZONE)->toDateString(),
            ]);

            return $last ? $assessment : $this->sign->handle($assessment, $hr);
        });

        if ($last) {
            return false;
        }

        if ($assessment->level !== RiskLevel::Low) {
            foreach (self::MEASURES[$role][$i] ?? [] as $measure) {
                $this->measure($offices, $hr, $assessment, $day, ...$measure);
            }
        }

        if (! $person->reservedForDoor) {
            DemoClock::at(($day + 2 + $i).' days', fn () => $this->acknowledge->handle($this->portal->open($this->portal->token($id)) ?? throw new LogicException('The portal of a demo person does not open.')), 18 * 60 + 30);
        }

        return true;
    }

    /** The Comisión proposes the measure as it signs; HR gives it an owner and a date, and follows it up. */
    private function measure(string $offices, int $hr, RiskAssessment $assessment, int $signedOn, MeasureKind $kind, MeasureStatus $status, int $reachedOn, int $dueOn): void
    {
        $today = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay();
        $measure = DemoClock::at("{$signedOn} days", fn (): PreventiveMeasure => $this->propose->handle($assessment, $kind, $kind->label().'.', $hr), 13 * 60);
        $follow = fn (MeasureStatus $to) => $this->saveMeasure->handle(new MeasureData($measure->id, $offices, null, null, null, null, $to, $hr, $today->addDays($dueOn)->toDateString()), null, $hr);
        DemoClock::at(($signedOn + 1).' days', fn (): PreventiveMeasure => $follow(MeasureStatus::Planned));

        if ($status !== MeasureStatus::Planned) {
            DemoClock::at("{$reachedOn} days", fn (): PreventiveMeasure => $follow($status));
        }
    }

    /**
     * HR launches the confidential survey of the center; everyone but the door answers it through their link over the next
     * days (whoever works standing answers every question) and it closes two weeks after it opened.
     *
     * @return int how many answered
     */
    private function survey(string $offices, int $hr): int
    {
        $survey = DemoClock::at('-'.self::SURVEY_DAYS_AGO.' days', function () use ($offices, $hr) {
            $survey = $this->surveys->launch($offices, CarbonImmutable::now(DemoClock::TIMEZONE)->addDays(14)->startOfDay(), $hr);

            // The invitation job would store each link's hash and mark it sent; it runs on the queue, so the story does it now.
            foreach (Participant::query()->where('campaign_id', $survey->id)->get() as $participant) {
                $participant->update(['token_hash' => Participant::hashToken($participant->surveyToken()), 'invited_at' => now(), 'invitation_status' => InvitationStatus::Sent]);
            }

            return $survey;
        });
        $people = [];

        foreach (DemoRoster::people() as $person) {
            $people[DemoRoster::employeeId($person->key)] = $person;
        }

        $answered = 0;
        $standing = [];

        $participants = Participant::query()->where('campaign_id', $survey->id)->get()
            ->sortBy(fn (Participant $participant): string => DemoRoster::sortKey($people[$participant->employee_id]->key))->values();

        foreach ($participants as $n => $participant) {
            $person = $people[$participant->employee_id];

            if ($person->reservedForDoor) {
                continue;
            }

            $role = isset(self::POSITIONS[$person->position]) ? $person->position : null;
            $place = $role === null ? 0 : ($standing[$role] = ($standing[$role] ?? -1) + 1);
            DemoClock::at((-self::SURVEY_DAYS_AGO + 1 + $n % 9).' days', fn () => $this->submit->handle($participant->surveyToken(), 'seating.survey', $this->answers($role, $place)), 8 * 60 + $n * 13 % 600);
            $answered++;
        }

        DemoClock::at('-'.(self::SURVEY_DAYS_AGO - 14).' days', fn () => $this->surveys->close($offices));

        return $answered;
    }

    /**
     * What a person answers: whoever does not work standing, «no» to the first question and nothing else. At the counter
     * they stand for hours and one cannot always sit; at the filing runs some do not know the pauses agreed; nobody was ever
     * kept from sitting; on two positions the pauses are not always kept.
     *
     * @return array<array-key, string>
     */
    private function answers(?string $role, int $place): array
    {
        if ($role === null) {
            return ['stands' => 'no'];
        }

        $knowsPauses = $role !== 'Auxiliar contable' || $place % 2 === 1;
        $answers = match ($role) {
            'Asistente comercial' => [1 => 'yes', 2 => $place === 0 ? 'no' : 'yes', 3 => 'no', 4 => 'yes', 5 => 'no', 6 => 'yes', 7 => 'yes', 8 => 'yes'],
            'Auxiliar contable' => [1 => 'yes', 2 => 'yes', 3 => 'no', 4 => $knowsPauses ? 'yes' : 'no', 5 => $knowsPauses ? 'no' : 'not_applicable', 6 => $place === 0 ? 'no' : 'yes', 7 => 'no', 8 => $place === 1 ? 'yes' : 'no'],
            default => [1 => 'yes', 2 => 'yes', 3 => 'no', 4 => 'yes', 5 => 'yes', 6 => 'yes', 7 => 'no', 8 => 'no'],
        };

        return ['stands' => 'yes'] + $answers;
    }

    /**
     * The nine characteristics of Quinta F, each saying whether the seat has it.
     *
     * @param  list<SeatFeature>  $has
     * @return array<string, bool>
     */
    private function features(array $has): array
    {
        $features = [];

        foreach (SeatFeature::cases() as $feature) {
            $features[$feature->value] = in_array($feature, $has, true);
        }

        return $features;
    }

    /**
     * The people of the offices who hold a position, in the roster's order.
     *
     * @return list<DemoPerson>
     */
    private function holders(string $role): array
    {
        return array_values(array_filter(DemoRoster::people(), fn (DemoPerson $person): bool => $person->centerKey === 'offices' && $person->position === $role));
    }
}
