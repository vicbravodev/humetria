<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Stories;

use App\Modules\Complaints\Actions\AcknowledgeComplaint;
use App\Modules\Complaints\Actions\AssignComplaint;
use App\Modules\Complaints\Actions\CloseCase;
use App\Modules\Complaints\Actions\DesignateHandler;
use App\Modules\Complaints\Actions\SendReporterMessage;
use App\Modules\Complaints\Actions\StartReview;
use App\Modules\Complaints\Actions\WriteToReporter;
use App\Modules\Complaints\Enums\ComplaintCategory;
use App\Modules\Complaints\Enums\ComplaintResolution;
use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Enums\IdentityMode;
use App\Modules\Complaints\Enums\RespondentKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintChannel;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoStory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Quejas y denuncias at Alba del Bajío. The owner opened the channel eight months ago and named HR to handle it too.
 * Since then people wrote in, all anonymously: the overtime and the load of the plant's second shift (both closed with
 * measures, the start of the intervention program), a complaint too vague to act on, the safety of the loading area and the
 * treatment of a night supervisor (in review; on this one the handler asked and the person answered from the follow-up),
 * one acknowledged a couple of days ago and one that came in yesterday. Nothing is dated today, so a visitor who files a
 * complaint from the poster has the whole day's cap; the channel stays open for that.
 *
 * Handling goes through the module's actions. Receiving does not: SubmitComplaint takes a ComplaintInput, which the module
 * keeps in Support, closed to the story, so the story writes the row SubmitComplaint writes (an anonymous complaint with a
 * random reference and the hash of a key nobody keeps, only the day) and assigns it through AssignComplaint, as SubmitComplaint
 * does; a drift guard in the test compares both. Opening the channel writes the row ActivateChannel writes, with an access
 * code derived from the application's key instead of a random one: the demo's poster and link survive every reset.
 */
final readonly class ComplaintsStory implements DemoStory
{
    /** Crockford's base 32, the alphabet of the module's codes (OpenChannel only finds a code written in it). */
    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const int OPENED_DAYS_AGO = 240;

    private const string PROCEDURE = <<<'TEXT'
        1. Cualquier persona que trabaja en Alba del Bajío puede presentar una queja o una denuncia desde el cartel o el enlace del canal, de forma anónima o confidencial.
        2. La reciben solo las personas que la dirección designó para atenderla; nadie atiende una queja contra su propio puesto.
        3. Acusamos recibo en un máximo de 3 días y damos una respuesta general en un máximo de 30 días.
        4. Quien presenta la queja le da seguimiento con su referencia y su clave; nadie más puede leerla.
        5. Nadie puede tomar represalias contra quien presenta una queja de buena fe.
        TEXT;

    /**
     * The cases: when each came in (days ago), where, what, against whom, and what happened to it.
     *
     * @var list<array{received: int, center: string, category: ComplaintCategory, against: RespondentKind, text: string, fate: string}>
     */
    private const array CASES = [
        ['received' => 175, 'center' => 'plant', 'category' => ComplaintCategory::WorkloadOrHours, 'against' => RespondentKind::Unknown, 'fate' => 'closed',
            'text' => 'En el turno 2 de producción la carga de trabajo no alcanza a salir en la jornada. Desde hace semanas nos quedamos casi todos los días y el ritmo de la línea no baja.'],
        ['received' => 150, 'center' => 'plant', 'category' => ComplaintCategory::WorkloadOrHours, 'against' => RespondentKind::Unknown, 'fate' => 'closed',
            'text' => 'Las horas extra del turno 2 no aparecen completas en el recibo de nómina. Nos piden quedarnos pero luego no se pagan todas las horas que trabajamos.'],
        ['received' => 60, 'center' => 'offices', 'category' => ComplaintCategory::CommunicationOrParticipation, 'against' => RespondentKind::Coworker, 'fate' => 'vague',
            'text' => 'En el área no nos avisan de los cambios a tiempo y luego nos llaman la atención por no saberlos.'],
        ['received' => 20, 'center' => 'distribution', 'category' => ComplaintCategory::Other, 'against' => RespondentKind::Unknown, 'fate' => 'review',
            'text' => 'En el área de carga los montacargas pasan muy cerca de los andenes donde trabajamos a pie y no hay señalamiento. Me preocupa la seguridad del área.'],
        ['received' => 14, 'center' => 'plant', 'category' => ComplaintCategory::Mistreatment, 'against' => RespondentKind::DirectSupervisor, 'fate' => 'follow-up',
            'text' => 'El trato de la supervisión del turno nocturno hacia el equipo es malo: levanta la voz y nos exhibe frente a los demás cuando la línea se retrasa.'],
        ['received' => 3, 'center' => 'plant', 'category' => ComplaintCategory::WorkloadOrHours, 'against' => RespondentKind::Unknown, 'fate' => 'acknowledged',
            'text' => 'Este mes otra vez hubo horas extra no pagadas en el turno nocturno. Quisiera saber cómo se están registrando.'],
        ['received' => 1, 'center' => 'monterrey', 'category' => ComplaintCategory::LackOfRecognition, 'against' => RespondentKind::Unknown, 'fate' => 'new',
            'text' => 'En la sucursal cumplimos las metas del trimestre y nadie nos dijo nada. Se siente que el esfuerzo no cuenta.'],
    ];

    private const string ASK = 'Gracias por escribir. Para revisar el caso, ¿nos puede decir en qué días de la semana ocurre con más frecuencia?';

    private const string REPLY = 'Sobre todo los jueves y viernes, al final del turno, cuando hay que entregar la producción de la semana.';

    private const string ANSWER_BACK = 'Gracias. Estamos revisando la situación con la gerencia de la planta y le escribiremos aquí cuando tengamos novedades.';

    private const array CLOSING = [
        'closed' => 'Revisamos el caso: se ajustó la plantilla del turno 2 y el registro de horas extra. Gracias por avisarnos.',
        'vague' => 'Con la información del caso no pudimos identificar la situación. Si vuelve a ocurrir, puede escribirnos con más detalle.',
    ];

    public function __construct(
        private DesignateHandler $designate,
        private AssignComplaint $assign,
        private AcknowledgeComplaint $acknowledge,
        private StartReview $review,
        private WriteToReporter $write,
        private SendReporterMessage $reporterMessage,
        private CloseCase $close,
    ) {}

    /**
     * The demo channel's access code: the same on every run with the same application key, unguessable without it.
     */
    public static function accessCode(): string
    {
        $bytes = hash_hmac('sha256', 'demo-complaints-channel', config()->string('app.key'), true);
        $code = '';

        for ($i = 0; $i < 16; $i++) {
            // 256 is a multiple of 32: every symbol is equally likely.
            $code .= self::ALPHABET[ord($bytes[$i]) % 32];
        }

        return $code;
    }

    public function tell(DemoContext $context): void
    {
        $channel = DemoClock::at('-'.self::OPENED_DAYS_AGO.' days', fn (): ComplaintChannel => $this->open($context));
        $hr = $context->hr->id;

        foreach (self::CASES as $case) {
            $complaint = DemoClock::at("-{$case['received']} days", fn (): Complaint => $this->receive($context, $channel, $case));
            $this->handle($complaint, $case['fate'], $case['received'], $hr);
        }

        Log::info('demo.complaints_seeded', ['cases' => count(self::CASES)]);
    }

    /** The owner activates the channel and, as ActivateChannel does, handles it; then names HR to handle it too. */
    private function open(DemoContext $context): ComplaintChannel
    {
        $channel = ComplaintChannel::query()->create([
            'tenant_id' => $context->tenantId,
            'access_code' => self::accessCode(),
            'procedure' => self::PROCEDURE,
            'acknowledge_within_days' => config()->integer('complaints.deadlines.acknowledge_days'),
            'resolve_within_days' => config()->integer('complaints.deadlines.resolve_days'),
            'retention_months' => config()->integer('complaints.retention.default_months'),
            'enabled_at' => now(),
            'published_on' => CarbonImmutable::now(DemoClock::TIMEZONE)->toDateString(),
            'paused_at' => null,
        ]);
        $this->designate->handle($context->owner->id, null, $context->owner->id);
        $this->designate->handle($context->hr->id, null, $context->owner->id);

        return $channel;
    }

    /**
     * What SubmitComplaint stores for an anonymous complaint, and its assignment to the handlers.
     *
     * @param  array{received: int, center: string, category: ComplaintCategory, against: RespondentKind, text: string, fate: string}  $case
     */
    private function receive(DemoContext $context, ComplaintChannel $channel, array $case): Complaint
    {
        return DB::transaction(function () use ($context, $channel, $case): Complaint {
            $complaint = Complaint::query()->create([
                'tenant_id' => $context->tenantId,
                'channel_id' => $channel->id,
                'reference' => self::randomCode(8),
                // The key is never kept: nobody follows these cases up from the public page.
                'access_key_hash' => hash('sha256', self::randomCode(20)),
                'type' => $case['category']->type(),
                'category' => $case['category'],
                'respondent_kind' => $case['against'],
                'work_center_id' => $context->center($case['center']),
                'identity_mode' => IdentityMode::Anonymous,
                'status' => ComplaintStatus::New,
                'description' => $case['text'],
                'received_on' => CarbonImmutable::now(DemoClock::TIMEZONE)->toDateString(),
            ]);
            $this->assign->handle($complaint);

            return $complaint;
        });
    }

    /** HR takes the case where its story leaves it, a day or more after it came in. */
    private function handle(Complaint $complaint, string $fate, int $received, int $hr): void
    {
        if ($fate === 'acknowledged') {
            DemoClock::at('-'.($received - 1).' days', fn () => $this->acknowledge->handle($complaint, $hr));
        } elseif ($fate === 'review') {
            DemoClock::at('-'.($received - 2).' days', fn () => $this->review->handle($complaint, $hr));
        } elseif ($fate === 'follow-up') {
            $this->followUp($complaint, $received, $hr);
        } elseif ($fate !== 'new') {
            $this->closeAfterReview($complaint, $fate, $received, $hr);
        }
    }

    /** The handler asks, the person answers from the follow-up page, and the handler answers back. */
    private function followUp(Complaint $complaint, int $received, int $hr): void
    {
        DemoClock::at('-'.($received - 1).' days', function () use ($complaint, $hr): void {
            $this->review->handle($complaint, $hr);
            $this->write->handle($complaint, self::ASK, $hr);
        });
        DemoClock::at('-'.($received - 3).' days', fn () => $this->reporterMessage->handle($complaint, self::REPLY));
        DemoClock::at('-'.($received - 4).' days', fn () => $this->write->handle($complaint, self::ANSWER_BACK, $hr));
    }

    private function closeAfterReview(Complaint $complaint, string $fate, int $received, int $hr): void
    {
        DemoClock::at('-'.($received - 2).' days', fn () => $this->review->handle($complaint, $hr));
        $resolution = $fate === 'vague' ? ComplaintResolution::InsufficientInformation : ComplaintResolution::MeasuresTaken;
        DemoClock::at('-'.($received - 25).' days', fn () => $this->close->handle($complaint, $resolution, $hr, self::CLOSING[$fate]));
    }

    private static function randomCode(int $length): string
    {
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= self::ALPHABET[random_int(0, 31)];
        }

        return $code;
    }
}
