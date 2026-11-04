<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Actions\RecordAudit;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Models\DisseminationRecord;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Disclosure\Models\ResultPublication;
use App\Modules\Results\Data\RiskBreakdown;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * The printable dossier of the disclosure of a work center, for an inspection (num. 10.2 and 10.4): what the board judges and
 * the evidence behind each row (the policy in force, the acknowledgments as a count, the physical means with their day, the state of
 * the link of the page, where each block of 5.7 comes from and when, and the results published). It lists what is recorded and
 * does not certify compliance. It can leave the system, so it names no person and carries no link: the detail of who confirmed
 * stays inside the application. Seeing it goes to the audit trail.
 */
final readonly class DossierPages
{
    private const string NOTE = 'Este documento lista lo registrado en la plataforma; no certifica el cumplimiento de la norma. Los acuses van como conteo: el detalle de quiénes confirmaron está dentro de la aplicación.';

    public function __construct(
        private WorkCenters $workCenters,
        private Board $board,
        private Requirements $requirements,
        private PolicyStanding $standing,
        private ProgramMeasureFeed $program,
        private ComplaintChannelFeed $channel,
        private Companies $companies,
        private RecordAudit $audit,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws ModelNotFoundException when the center is not one in use of this company.
     */
    public function show(string $workCenterId): array
    {
        $center = $this->workCenters->findActive($workCenterId) ?? throw new ModelNotFoundException;
        $facts = $this->board->facts([$center])[$center->id];
        $this->audit->handle('disclosure.dossier_viewed', null, ['work_center_id' => $center->id]);
        $link = PageLink::query()->first();

        return [
            'header' => ['company' => $this->companies->current()->tradeName, 'legalName' => $this->workCenters->profile($center->id)->legalName ?? '', 'workCenter' => $center->name, 'issuedOn' => Days::moment(now())],
            'requirements' => array_map(fn (Requirement $requirement): array => $requirement->toArray(), $this->requirements->for($facts)),
            'policy' => $facts->policy === null ? null : ['version' => $facts->policy->version, 'publishedOn' => Days::moment($facts->policy->publishedAt), 'covers' => $facts->policy->covers],
            'acknowledgments' => $facts->acknowledgments,
            'records' => array_values(DisseminationRecord::query()->where('work_center_id', $center->id)->orderByDesc('done_on')->orderByDesc('id')->limit(100)->get()->map(fn (DisseminationRecord $record): array => [
                'mediumLabel' => $record->medium->label(),
                'items' => $record->covered_items,
                'doneOn' => Days::calendar($record->done_on),
                'note' => $record->note,
            ])->all()),
            'link' => $link === null ? null : ['state' => ['value' => $link->status()->value, 'label' => $link->status()->label()], 'createdOn' => Days::moment($link->created_at), 'expiresOn' => $link->expires_at === null ? null : Days::moment($link->expires_at)],
            'contents' => $this->contents($center->id, $facts),
            'results' => $this->results($facts),
            'note' => self::NOTE,
        ];
    }

    /**
     * Where each block of 5.7 comes from in this center and when the company's own text was published.
     *
     * @return list<array{inciso: string, label: string, sources: list<array{origin: string, publishedOn: string|null}>}>
     */
    private function contents(string $centerId, CenterFacts $facts): array
    {
        $blocks = [];

        foreach (DocumentKind::cases() as $kind) {
            if (! $kind->isBlock()) {
                continue;
            }

            $sources = [];
            $text = $this->standing->ofKind($kind, [$centerId])[$centerId] ?? null;

            if ($text !== null) {
                $sources[] = ['origin' => 'Texto de RH', 'publishedOn' => Days::moment($text->publishedAt)];
            }

            if ($kind->inciso() !== 'd' && $facts->results?->showMeasures === true && $facts->results->shown !== null && array_any($this->program->forCampaign($facts->results->shown->id), fn (PublicMeasure $measure): bool => $measure->inciso === $kind->inciso())) {
                $sources[] = ['origin' => 'Del Programa de intervención', 'publishedOn' => null];
            }

            if ($kind->inciso() === 'd' && $this->channel->current() !== null) {
                $sources[] = ['origin' => 'Del canal de quejas', 'publishedOn' => null];
            }

            $blocks[] = ['inciso' => $kind->inciso(), 'label' => $kind->label(), 'sources' => $sources];
        }

        return $blocks;
    }

    /**
     * The results published for the center: the evaluation, the day RH published it and whether figures are shown.
     *
     * @return array{closedOn: string|null, publishedOn: string, state: string}|null
     */
    private function results(CenterFacts $facts): ?array
    {
        $fact = $facts->results;

        if ($fact === null || $fact->shown === null || ! in_array($fact->shownBreakdown?->state, [RiskBreakdown::AVAILABLE, RiskBreakdown::BELOW_MINIMUM], true)) {
            return null;
        }

        $publication = ResultPublication::query()->where('campaign_id', $fact->shown->id)->whereNull('withdrawn_at')->first(['published_at']);

        return $publication === null ? null : ['closedOn' => $fact->shown->closedOn, 'publishedOn' => Days::moment($publication->published_at), 'state' => $fact->shownBreakdown->state];
    }
}
