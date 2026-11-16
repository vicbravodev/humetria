<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Stories;

use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Disclosure\Actions\Acknowledge;
use App\Modules\Disclosure\Actions\IssuePageLink;
use App\Modules\Disclosure\Actions\PublishContent;
use App\Modules\Disclosure\Actions\PublishPolicy;
use App\Modules\Disclosure\Actions\PublishResults;
use App\Modules\Disclosure\Actions\RecordDissemination;
use App\Modules\Disclosure\Actions\RecordManualAcknowledgments;
use App\Modules\Disclosure\Actions\RequestAcknowledgments;
use App\Modules\Disclosure\Actions\SaveDraft;
use App\Modules\Disclosure\Actions\StartContentDraft;
use App\Modules\Disclosure\Actions\StartPolicyDraft;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\Medium;
use App\Modules\Disclosure\Models\Document;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Database\Seeders\Demo\DemoStory;
use Illuminate\Support\Facades\Log;

/**
 * Difusión at Alba del Bajío. Before the first evaluation, HR published the policy of Guía IV with its responsibles and
 * resources, and the blocks of 5.7 the company writes (violence, prevention, complaints and health effects), to every center;
 * every person was asked to confirm it. Over the next two weeks the production shifts of the plant signed it on paper and
 * everyone else from their link; the people kept for the door have it pending, so a visitor confirms it live. When the first
 * evaluation closed, HR published its results to each center that has them (Monterrey only applied the Guía I), the plant's
 * with the measures of its Programa, and told it in a meeting. A poster in each center and the permanent page of the workers
 * complete the disclosure.
 */
final readonly class DisclosureStory implements DemoStory
{
    private const int PUBLISHED_DAYS_AGO = 200;

    private const array SHIFTS = ['Producción turno 1', 'Producción turno 2'];

    private const string RESPONSIBLES = 'La dirección general, la gerencia de cada centro y la jefatura de Recursos Humanos aplican esta política; los supervisores y jefes de área la ponen en práctica con sus equipos y reciben capacitación para hacerlo.';

    private const string RESOURCES = 'Un presupuesto anual para capacitación y sensibilización, el canal de quejas y denuncias, el tiempo de las reuniones de arranque de turno para difundirla y el acompañamiento de Recursos Humanos en cada centro.';

    /** The blocks of 5.7 the company writes; the health effects keep the base text the module gives. */
    private const array BLOCKS = [
        'violence_measures' => 'Toda queja por malos tratos, acoso o violencia laboral se recibe por el canal de quejas y la atienden personas designadas por la dirección, nunca alguien contra su propio puesto. Se investiga con confidencialidad, se protege a quien la presenta y las conductas comprobadas se sancionan conforme al Reglamento Interior de Trabajo.',
        'prevention_measures' => 'Revisamos las cargas de trabajo y la rotación de turnos con cada supervisor, fijamos un tope de horas extra por semana, damos capacitación a los mandos sobre liderazgo y trato, y evaluamos los factores de riesgo psicosocial en cada centro para ajustar el programa de intervención.',
        'complaint_mechanism' => 'Las quejas y denuncias se presentan, de forma anónima o confidencial, desde el cartel o el enlace del canal de quejas, disponible para todo el personal. También puede acudir con Recursos Humanos de su centro.',
    ];

    public function __construct(
        private StartPolicyDraft $startPolicy,
        private StartContentDraft $startContent,
        private SaveDraft $save,
        private PublishPolicy $publishPolicy,
        private PublishContent $publishContent,
        private RequestAcknowledgments $request,
        private Acknowledge $acknowledge,
        private RecordManualAcknowledgments $manual,
        private RecordDissemination $dissemination,
        private PublishResults $results,
        private IssuePageLink $pageLink,
    ) {}

    public function tell(DemoContext $context): void
    {
        $hr = $context->hr->id;
        $policy = DemoClock::at('-'.self::PUBLISHED_DAYS_AGO.' days', fn (): Document => $this->publish($hr));
        $confirmed = $this->confirm($policy, $hr);

        foreach (array_keys(DemoRoster::CENTERS) as $center) {
            DemoClock::at('-'.(self::PUBLISHED_DAYS_AGO - 2).' days', fn () => $this->dissemination->handle(
                $context->center($center), Medium::Poster->value, ['a', 'b', 'c', 'd', 'f'], CarbonImmutable::now(DemoClock::TIMEZONE)->toDateString(),
                'Cartel de la política y de las medidas en el comedor y en la entrada.', $hr,
            ));
        }

        $published = $this->publishResults($context, $hr);
        Log::info('demo.disclosure_seeded', ['confirmed' => $confirmed, 'results_published' => $published]);
    }

    /** HR writes the policy from the example of Guía IV and the blocks of 5.7, publishes them to every center and asks for confirmations. */
    private function publish(int $hr): Document
    {
        $draft = $this->startPolicy->handle($hr);
        $this->save->handle($draft->id, array_map(fn (array $section): array => [
            'key' => $section['key'],
            'text' => match ($section['key']) {
                'responsibles' => self::RESPONSIBLES,
                'resources' => self::RESOURCES,
                default => $section['text'],
            },
            'items' => $section['items'],
        ], $draft->content));
        $policy = $this->publishPolicy->handle($draft->id, null, ['a', 'b', 'c'], true, $hr);

        foreach ([...array_keys(self::BLOCKS), DocumentKind::HealthEffects->value] as $kind) {
            $block = $this->startContent->handle(DocumentKind::from($kind), $hr);

            if (isset(self::BLOCKS[$kind])) {
                $this->save->handle($block->id, [['key' => 'body', 'text' => self::BLOCKS[$kind], 'items' => []]]);
            }

            $this->publishContent->handle($block->id, null, $hr);
        }

        // The listener of PolicyPublished asks too, later on the queue; asking twice adds nothing.
        $this->request->handle($policy->id);
        $this->pageLink->handle($hr);

        return $policy;
    }

    /**
     * The plant's shifts sign on paper at a start-of-shift meeting; the rest confirm from their link over two weeks, and
     * whoever joined later, days after their hire. Nobody kept for the door confirms.
     */
    private function confirm(Document $policy, int $hr): int
    {
        $shifts = [];
        $count = 0;

        foreach (DemoRoster::people() as $n => $person) {
            if ($person->reservedForDoor) {
                continue;
            }

            $id = DemoRoster::employeeId($person->key);

            // Whoever joined after the publication confirms from their link a few days after their hire, never on paper.
            $late = $person->tenureDays < self::PUBLISHED_DAYS_AGO;

            if ($this->onShift($person) && ! $late) {
                $shifts[] = $id;
            } else {
                $day = $late ? $person->tenureDays - 3 : self::PUBLISHED_DAYS_AGO - 1 - $n % 14;
                DemoClock::at("-{$day} days", fn () => $this->acknowledge->handle($id, $policy->id), 8 * 60 + $n % 600);
                $count++;
            }
        }

        $count += DemoClock::at('-'.(self::PUBLISHED_DAYS_AGO - 5).' days', fn (): int => $this->manual->handle(
            $policy->id, $shifts, CarbonImmutable::now(DemoClock::TIMEZONE)->toDateString(), 'Lista de firmas de la reunión de arranque de turno.', $hr,
        ));

        return $count;
    }

    /**
     * Once the first evaluation closed, HR publishes its results to each center that has them, the plant's with the measures
     * of its Programa, and tells them at a meeting.
     */
    private function publishResults(DemoContext $context, int $hr): int
    {
        $plant = $context->center('plant');
        $published = 0;

        foreach (Campaign::query()->where('kind', CampaignKind::Nom035Evaluation)->whereNotNull('closed_at')->orderBy('closed_at')->get() as $campaign) {
            if ($campaign->instruments === ['nom035.guide-i']) {
                continue;
            }

            DemoClock::at('-165 days', function () use ($campaign, $plant, $hr): void {
                $this->results->handle($campaign->id, $hr, $campaign->work_center_id === $plant);
                $this->dissemination->handle(
                    $campaign->work_center_id, Medium::Meeting->value, ['e'], CarbonImmutable::now(DemoClock::TIMEZONE)->toDateString(),
                    'Reunión con el personal para presentar los resultados de la evaluación.', $hr,
                );
            });
            $published++;
        }

        return $published;
    }

    private function onShift(DemoPerson $person): bool
    {
        return $person->centerKey === 'plant' && in_array($person->area, self::SHIFTS, true);
    }
}
