<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Actions\RecordAudit;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * The NOM-035 dossier of one work center: the six rows of the inspection table with their state and detail, and for each row
 * that is met (or part way) a link to the printable evidence of the module that keeps it. It is put together from the rows of the
 * route, so it never reads a table of another module and carries neither a name nor an answer: the Guía I channeling is a link
 * to the page that already asks for `view-trauma-screenings`, never a list. Opening it goes to the audit trail.
 */
final readonly class Nom035Dossier
{
    /** The label of the evidence link of a stage when the link is the row's own way in (stages 2, 3, 5 and 6). */
    private const array OWN_EVIDENCE = [2 => 'Resultados de la evaluación', 3 => 'Canalización', 5 => 'Programa de intervención', 6 => 'Informe 7.7'];

    public function __construct(private RoutePage $page, private WorkCenters $workCenters, private RecordAudit $audit) {}

    /**
     * @return array<string, mixed>
     */
    public function build(string $tenantSlug, WorkCenterSummary $center, bool $seesScreenings, bool $readyOnly): array
    {
        $record = ['work_center_id' => $center->id, 'ready_only' => $readyOnly];
        $this->audit->handle('nom035.dossier_viewed', null, $record);
        Log::info('nom035.dossier_viewed', $record);

        $rows = array_map(fn (array $row): array => [
            'numeral' => $row['numeral'],
            'title' => $row['title'],
            'state' => $row['state'],
            'detail' => $row['detail'],
            'evidence' => $this->evidence($tenantSlug, $center, $row),
        ], $this->page->rows($tenantSlug, $center, $seesScreenings));

        return [
            'center' => ['id' => $center->id, 'name' => $center->name, 'address' => $this->workCenters->profile($center->id)->address ?? ''],
            'issuedOn' => str_replace('.', '', CarbonImmutable::now(config()->string('app.display_timezone'))->isoFormat('D MMM YYYY')),
            'readyOnly' => $readyOnly,
            'rows' => array_values($readyOnly ? array_filter($rows, fn (array $row): bool => $row['state'] === 'done') : $rows),
        ];
    }

    /**
     * Where the evidence of a row that exists is printed: the disclosure dossier for the policy and the sharing, the page of the
     * stage for the rest. Only for whoever may open it, and not for a row that is missing or does not apply.
     *
     * @param  array{stage: int, state: string, href: string|null}  $row
     * @return array{label: string, href: string}|null
     */
    private function evidence(string $tenantSlug, WorkCenterSummary $center, array $row): ?array
    {
        if (! in_array($row['state'], ['done', 'partial'], true)) {
            return null;
        }

        if (in_array($row['stage'], [1, 4], true)) {
            return Gate::allows('manage-disclosure') ? ['label' => 'Expediente de difusión', 'href' => route('tenant.disclosure.dossier', [$tenantSlug, $center->id])] : null;
        }

        return $row['href'] === null ? null : ['label' => self::OWN_EVIDENCE[$row['stage']] ?? '', 'href' => $row['href']];
    }
}
