<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Stories;

use App\Modules\Instruments\Data\Favorable;
use App\Modules\Telework\Actions\Acknowledge;
use App\Modules\Telework\Actions\DeclareApplicability;
use App\Modules\Telework\Actions\JoinRoster;
use App\Modules\Telework\Actions\OpenPortal;
use App\Modules\Telework\Actions\PublishChecklist;
use App\Modules\Telework\Actions\PublishDocument;
use App\Modules\Telework\Actions\RecordManualAcknowledgements;
use App\Modules\Telework\Actions\RegisterAspirant;
use App\Modules\Telework\Actions\RegisterEquipment;
use App\Modules\Telework\Actions\RegisterTraining;
use App\Modules\Telework\Actions\RequestAcknowledgements;
use App\Modules\Telework\Actions\RequestChecklist;
use App\Modules\Telework\Actions\RequestEvidence;
use App\Modules\Telework\Actions\RespondEquipment;
use App\Modules\Telework\Actions\ReviewWorkplace;
use App\Modules\Telework\Actions\SubmitChecklist;
use App\Modules\Telework\Data\AddressData;
use App\Modules\Telework\Data\RosterData;
use App\Modules\Telework\Enums\ChecklistStatus;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Enums\EquipmentKind;
use App\Modules\Telework\Enums\EvidenceKind;
use App\Modules\Telework\Enums\MaritalStatus;
use App\Modules\Telework\Enums\ReviewDecision;
use App\Modules\Telework\Enums\Section;
use App\Modules\Telework\Enums\TrainingModality;
use App\Modules\Telework\Enums\TrainingTopic;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Telework\Models\EquipmentItem;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Workplace;
use Carbon\CarbonImmutable;
use Closure;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Database\Seeders\Demo\DemoStory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * NOM-037 at the offices. A little over a year ago the offices declared telework: their Comisión (constituted in the Ley
 * Silla story, with the head of HR) published the base checklist and HR published the policy and the risk information, as
 * PDFs. Everyone signed the policy on paper but the people kept for the door, who have it pending in their portal. Twelve
 * people of finance, HR, sales and systems telework: six for a year (their periodic Sección II was asked and answered, but
 * one still owes it and their workplace lapsed), four since this year, and two aspirants: one sent their Sección I and the
 * Comisión asked for photos, the other registered days ago and has the list pending. Everyone in the roster has their
 * equipment (one item still waits for its person), confirmed the risk information from their portal and took the trainings.
 */
final readonly class TeleworkStory implements DemoStory
{
    private const int PUBLISHED_DAYS_AGO = 420;

    /** How long before the expiry of a validation the daily review asks for the Sección II (ScheduleReviews::WARNING_DAYS). */
    private const int WARNING_DAYS = 30;

    /** The job titles whose people telework, the activities they do from home. */
    private const array ACTIVITIES = [
        'Contador' => 'Conciliaciones bancarias, cierres contables y declaraciones de impuestos.',
        'Analista de recursos humanos' => 'Reclutamiento, programas de capacitación y atención al personal por videollamada.',
        'Ejecutivo de ventas' => 'Seguimiento de clientes, cotizaciones y pedidos por teléfono y correo.',
        'Desarrollador' => 'Desarrollo y mantenimiento de los sistemas internos de la empresa.',
    ];

    /**
     * Who teleworks, in the roster's order of the twelve people of those job titles not kept for the door: how many days ago
     * HR registered them, their share of the working day, and where they are now: a year in the roster (`veteran`), the same
     * but owing the periodic Sección II (`lapsed`), in the roster since this year (`recent`), an aspirant whose list waits
     * for the Comisión (`review`) or one who has not answered it (`new`).
     *
     * @var list<array{int, int, 'veteran'|'lapsed'|'recent'|'review'|'new'}>
     */
    private const array PATHS = [
        [410, 60, 'veteran'], [408, 80, 'veteran'], [406, 60, 'veteran'], [404, 100, 'veteran'], [402, 60, 'veteran'], [400, 80, 'lapsed'],
        [10, 60, 'review'], [150, 80, 'recent'], [140, 60, 'recent'], [3, 60, 'new'], [130, 100, 'recent'], [120, 80, 'recent'],
    ];

    /**
     * The homes they telework from: street, number, neighborhood, postal code and municipality, all in Querétaro.
     *
     * @var list<array{string, string, string, string, string}>
     */
    private const array HOMES = [
        ['Paseo de la Constitución', '118', 'Juriquilla', '76230', 'Querétaro'],
        ['Hacienda El Refugio', '42', 'El Refugio', '76146', 'Querétaro'],
        ['Circuito Milenio', '305', 'Milenio III', '76060', 'Querétaro'],
        ['Avenida Fray Luis de León', '1220', 'Centro Sur', '76090', 'Querétaro'],
        ['Paseo Jurica', '77', 'Jurica', '76100', 'Querétaro'],
        ['Cerro de las Campanas', '15', 'Álamos 2a Sección', '76160', 'Querétaro'],
        ['Calle Carretas', '9', 'Carretas', '76050', 'Querétaro'],
        ['Avenida Candiles', '260', 'Lomas de Casa Blanca', '76080', 'Querétaro'],
        ['Avenida Cimatario', '501', 'Cimatario', '76030', 'Querétaro'],
        ['Paseo Zibatá', '34', 'Zibatá', '76269', 'El Marqués'],
        ['Avenida Josefa Ortiz', '88', 'El Pueblito', '76900', 'Corregidora'],
        ['Calle Tejeda', '140', 'Tejeda', '76904', 'Corregidora'],
    ];

    /** Who found a risk in their Sección I (by place in PATHS): the Comisión validated their place with a plan. */
    private const array WITH_PLAN = [4, 10];

    public function __construct(
        private DeclareApplicability $applicability,
        private PublishChecklist $publishChecklist,
        private PublishDocument $publishDocument,
        private RequestAcknowledgements $requestAcks,
        private RecordManualAcknowledgements $manualAcks,
        private Acknowledge $acknowledge,
        private RegisterAspirant $register,
        private OpenPortal $openPortal,
        private SubmitChecklist $submit,
        private ReviewWorkplace $review,
        private RequestEvidence $evidence,
        private RequestChecklist $requestChecklist,
        private RegisterEquipment $equipment,
        private RespondEquipment $respond,
        private JoinRoster $join,
        private RegisterTraining $training,
    ) {}

    public function tell(DemoContext $context): void
    {
        $hr = $context->hr->id;
        $offices = $context->center('offices');
        [$policy, $risk] = DemoClock::at('-'.self::PUBLISHED_DAYS_AGO.' days', fn (): array => $this->publish($offices, $hr));
        DemoClock::at('-'.(self::PUBLISHED_DAYS_AGO - 1).' days', fn () => $this->acknowledge->inPanel($hr, $risk->id, null));
        $this->sign($policy, $hr);

        $joined = [];

        foreach ($this->teleworkers() as $n => $person) {
            [$daysAgo, $percentage, $path] = self::PATHS[$n];
            $teleworker = $this->telework($person, $n, $daysAgo, $percentage, $path, $risk, $hr);

            if (in_array($path, ['veteran', 'lapsed', 'recent'], true)) {
                $joined[$daysAgo] = $teleworker->employee_id;
            }
        }

        $trainings = 0;

        foreach ([380 => TrainingModality::InPerson, 100 => TrainingModality::Remote] as $daysAgo => $modality) {
            $attendees = array_values(array_filter($joined, fn (int $since): bool => $since > $daysAgo, ARRAY_FILTER_USE_KEY));
            DemoClock::at("-{$daysAgo} days", fn () => $this->training->handle($offices, CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay(), $modality, TrainingTopic::cases(), $attendees, null, $hr));
            $trainings++;
        }

        Log::info('demo.telework_seeded', ['teleworkers' => count(self::PATHS), 'in_roster' => count($joined), 'trainings' => $trainings]);
    }

    /**
     * The offices declare telework, the Comisión publishes its base checklist, and HR publishes the policy (every item of 5.2)
     * and the risk information as PDFs and asks for their confirmations.
     *
     * @return array{TeleworkDocument, TeleworkDocument} the policy and the risk information
     */
    private function publish(string $offices, int $hr): array
    {
        $this->applicability->handle($offices, true, $hr);
        $this->publishChecklist->handle(Checklist::query()->where('work_center_id', $offices)->where('status', ChecklistStatus::Draft)->sole()->id, $hr);
        $policy = $this->document(DocumentKind::Policy, $offices, array_keys(DocumentKind::Policy->items()), $hr);
        $risk = $this->document(DocumentKind::RiskInformation, $offices, [], $hr);
        // The listener of DocumentPublished asks too, later on the queue; asking twice adds nothing.
        $this->requestAcks->handle([$policy->id, $risk->id]);

        return [$policy, $risk];
    }

    /**
     * Uploads a document as a one-page PDF with its title.
     *
     * @param  list<string>  $items
     */
    private function document(DocumentKind $kind, string $offices, array $items, int $hr): TeleworkDocument
    {
        $file = sys_get_temp_dir().'/demo-telework-'.Str::uuid().'.pdf';

        try {
            file_put_contents($file, $this->pdf([$kind->label(), 'Manufacturas Alba del Bajío, S.A. de C.V.', 'Oficinas Querétaro', 'Documento de la empresa de demostración de Humetria.']));

            return $this->publishDocument->handle($kind, $file, [$offices], $items, $hr)[0];
        } finally {
            unlink($file);
        }
    }

    /**
     * Everyone of the offices signs the policy on paper five days after it came out, but the people kept for the door, who
     * confirm it live, and whoever joined later, who confirms it from their link days after their hire.
     */
    private function sign(TeleworkDocument $policy, int $hr): void
    {
        $paper = [];

        foreach (DemoRoster::people() as $person) {
            if ($person->centerKey !== 'offices' || $person->reservedForDoor) {
                continue;
            }

            $id = DemoRoster::employeeId($person->key);

            if ($person->tenureDays > self::PUBLISHED_DAYS_AGO - 5) {
                $paper[] = $id;
            } else {
                DemoClock::at('-'.($person->tenureDays - 3).' days', fn () => $this->acknowledge->handle($this->openPortal->handle($id), $policy->id, null));
            }
        }

        DemoClock::at('-'.(self::PUBLISHED_DAYS_AGO - 5).' days', fn (): int => $this->manualAcks->handle(
            $policy->id, $paper, CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay(), 'Lista de firmas de la junta de arranque del teletrabajo.', $hr,
        ));
    }

    /**
     * One teleworker's year: registered as an aspirant, they answer their Sección I; the Comisión validates the place (with a
     * plan if they found a risk); HR registers their equipment, which they confirm, and moves them into the roster; they
     * confirm the risk information. A year later the daily review asks for the Sección II, which the Comisión validates
     * again, unless the person still owes it.
     *
     * @param  'veteran'|'lapsed'|'recent'|'review'|'new'  $path
     */
    private function telework(DemoPerson $person, int $n, int $daysAgo, int $percentage, string $path, TeleworkDocument $risk, int $hr): Teleworker
    {
        $id = DemoRoster::employeeId($person->key);
        [$street, $number, $neighborhood, $postalCode, $municipality] = self::HOMES[$n];
        $home = new AddressData($street, $number, null, $neighborhood, $postalCode, $municipality, 'QUE');
        $d = -$daysAgo;

        $teleworker = $this->on($d, $n, fn (): Teleworker => $this->register->handle($id, $percentage, [$home], $hr));
        $workplace = Workplace::query()->where('teleworker_id', $teleworker->id)->sole();

        if ($path === 'new') {
            return $teleworker;
        }

        $risky = in_array($n, self::WITH_PLAN, true);
        $application = $this->on($d + 3, $n, fn (): ChecklistApplication => $this->answer($id, $workplace, Section::One, $risky));

        if ($path === 'review') {
            $this->on($d + 5, $n, fn () => $this->evidence->handle($workplace, EvidenceKind::Photos, 'Por favor mande una foto de su escritorio y de la iluminación de su lugar de trabajo.', $hr));

            return $teleworker;
        }

        $validated = $d + 7;
        $this->on($validated, $n, fn () => $this->decide($workplace, $application, $risky, $hr));
        $this->on($d + 8, $n, fn (): EquipmentItem => $this->equipment->handle($teleworker, EquipmentKind::Chair, 'Silla ergonómica con soporte lumbar', CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay(), $hr));
        $this->on($d + 8, $n, fn (): EquipmentItem => $this->equipment->handle($teleworker, EquipmentKind::Computer, 'Laptop de 14 pulgadas con base y teclado', CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay(), $hr));

        foreach (EquipmentItem::query()->where('teleworker_id', $teleworker->id)->get() as $item) {
            $this->on($d + 10, $n, fn () => $this->respond->handle($this->openPortal->handle($id), $item->id, true, null, null));
        }

        $this->on($d + 12, $n, fn () => $this->join->handle($teleworker, new RosterData(
            MaritalStatus::cases()[$n % 3], self::ACTIVITIES[$person->position], "{$person->position} a distancia, con computadora y teléfono de la empresa.", sprintf('55 0000 %04d', $n), $home, null,
        ), $hr));
        $this->on($d + 13, $n, fn () => $this->acknowledge->handle($this->openPortal->handle($id), $risk->id, null));

        if ($n === count(self::PATHS) - 1) {
            // The last to join gets a second item a few days ago, still waiting for their confirmation.
            DemoClock::at('-5 days', fn (): EquipmentItem => $this->equipment->handle($teleworker, EquipmentKind::ErgonomicAccessory, 'Reposapiés y soporte para laptop', CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay(), $hr));
        }

        if ($path !== 'recent') {
            // About a year after the validation, thirty days before it lapses, the daily review asks for the Sección II.
            $asked = $validated + 365 - self::WARNING_DAYS;
            $periodic = $this->on($asked, $n, fn (): ChecklistApplication => $this->requestChecklist->handle($workplace, Section::Two));

            if ($path === 'veteran') {
                $this->on($asked + 4, $n, fn (): ChecklistApplication => $this->answer($id, $workplace, Section::Two, false));
                $this->on($asked + 9, $n, fn () => $this->decide($workplace, $periodic->refresh(), false, $hr));
            }
        }

        return $teleworker;
    }

    /**
     * The person answers a section of their list from their portal, with the consent: every question with its favorable
     * answer, unless they found a risk, which they describe.
     */
    private function answer(string $employeeId, Workplace $workplace, Section $section, bool $risky): ChecklistApplication
    {
        $application = ChecklistApplication::query()->where('workplace_id', $workplace->id)->where('section', $section)->whereNull('submitted_at')->sole();
        $questions = ChecklistQuestion::query()->where('checklist_id', $application->checklist_id)->where('section', $section->value)->orderBy('position')->get();
        $flagged = $risky ? $questions->first(fn (ChecklistQuestion $question): bool => $question->favorable === Favorable::Yes && ! $question->sensitive)?->id : null;
        $answers = [];

        foreach ($questions as $question) {
            $answers[$question->id] = match (true) {
                $question->id === $flagged => 'no',
                $question->favorable === Favorable::No => 'no',
                default => 'yes',
            };
        }

        $notes = $flagged === null ? [] : [$flagged => 'La luz de la ventana da directo a la pantalla por la tarde y no hay cortina.'];
        $this->submit->handle($this->openPortal->handle($employeeId), $application->id, true, $answers, $notes, null, []);

        return $application;
    }

    /** The Comisión validates a place; with a risk found, with an action plan due in two months. */
    private function decide(Workplace $workplace, ChecklistApplication $application, bool $risky, int $hr): void
    {
        $this->review->handle(
            $workplace, $application, $risky ? ReviewDecision::ValidWithPlan : ReviewDecision::Valid,
            'Lista revisada por la Comisión.', $risky ? 'Instalar una cortina o persiana y mover el escritorio de frente a la ventana.' : null,
            $risky ? CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay()->addMonths(2) : null, $hr,
        );
    }

    /**
     * Runs a step of a teleworker's story on a day, at a minute of the morning of their own.
     *
     * @template T
     *
     * @param  Closure(): T  $then
     * @return T
     */
    private function on(int $day, int $n, Closure $then): mixed
    {
        return DemoClock::at("{$day} days", $then, 10 * 60 + $n * 17);
    }

    /**
     * The twelve people who telework: of finance, HR, sales and systems, the ones not kept for the door, in the roster's order.
     *
     * @return list<DemoPerson>
     */
    private function teleworkers(): array
    {
        return array_slice(array_values(array_filter(DemoRoster::people(), fn (DemoPerson $person): bool => $person->centerKey === 'offices' && isset(self::ACTIVITIES[$person->position]) && ! $person->reservedForDoor)), 0, count(self::PATHS));
    }

    /**
     * A one-page PDF with a line of text each: what the person downloads from their portal.
     *
     * @param  list<string>  $lines
     */
    private function pdf(array $lines): string
    {
        $text = implode(' T* ', array_map(fn (string $line): string => '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], mb_convert_encoding($line, 'Windows-1252', 'UTF-8')).') Tj', $lines));
        $stream = "BT /F1 14 Tf 18 TL 72 720 Td {$text} ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
