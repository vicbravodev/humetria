<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Data\PendingChecklist;
use App\Modules\Telework\Data\PendingDocument;
use App\Modules\Telework\Data\PendingEquipment;
use App\Modules\Telework\Data\PendingPhotoRequest;
use App\Modules\Telework\Data\PendingVisit;
use App\Modules\Telework\Data\PortalPendingData;
use App\Modules\Telework\Enums\EquipmentStatus;
use App\Modules\Telework\Enums\EvidenceKind;
use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Models\EquipmentItem;
use App\Modules\Telework\Models\EvidenceRequest;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Models\VisitAuthorization;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Models\WorkplaceReview;

/**
 * The pending items of a person's portal: the checklists of their workplaces, the photos the Comisión asked for and the
 * visits to authorize; documents and equipment. A sent checklist is only ever "En revisión de la
 * Comisión" or "Revisada": the outcome is told by RH, not by the portal (criterio C-11, designer decision 7).
 */
final readonly class PortalPending
{
    public function __construct(private WorkplaceAddress $address) {}

    public function for(OpenedPortal $portal): PortalPendingData
    {
        $documents = $this->documents($portal);

        if ($portal->teleworkerId === null) {
            return new PortalPendingData([], [], [], [], $documents);
        }

        $equipment = $this->equipment($portal->teleworkerId);

        $workplaces = Workplace::query()->where('teleworker_id', $portal->teleworkerId)->whereNull('retired_at')->with('applications')->orderBy('created_at')->orderBy('id')->get();
        $lines = $workplaces->mapWithKeys(fn (Workplace $workplace): array => [$workplace->id => $this->address->line($workplace)])->all();
        $ids = array_keys($lines);
        $reviewed = WorkplaceReview::query()->whereIn('workplace_id', $ids)->whereNotNull('application_id')->pluck('application_id')->all();
        $items = ['pending' => [], 'sent' => []];

        foreach ($workplaces as $workplace) {
            foreach ($workplace->applications->whereNull('cancelled_at')->sortBy([['requested_at', 'asc'], ['id', 'asc']]) as $application) {
                $sent = $application->submitted_at !== null;
                $label = match (true) {
                    ! $sent => 'Pendiente de contestar',
                    in_array($application->id, $reviewed, true) => 'Revisada',
                    default => 'En revisión de la Comisión',
                };
                $items[$sent ? 'sent' : 'pending'][] = new PendingChecklist($application->id, $lines[$workplace->id], $application->section->value, $label);
            }
        }

        $photos = EvidenceRequest::query()->whereIn('workplace_id', $ids)->where('kind', EvidenceKind::Photos)->whereNull('fulfilled_at')->orderBy('requested_at')->orderBy('id')->get();
        $visits = VisitAuthorization::query()->whereIn('workplace_id', $ids)->whereNull('cancelled_at')->whereNull('confirmed_at')->whereNull('document_path')->orderBy('requested_at')->orderBy('id')->get();

        return new PortalPendingData(
            $items['pending'],
            $items['sent'],
            array_values($photos->map(fn (EvidenceRequest $request): PendingPhotoRequest => new PendingPhotoRequest($request->id, $lines[$request->workplace_id] ?? '', $request->note))->all()),
            array_values($visits->map(fn (VisitAuthorization $authorization): PendingVisit => new PendingVisit($authorization->id, $lines[$authorization->workplace_id] ?? ''))->all()),
            $documents,
            $equipment,
        );
    }

    /** @return list<PendingEquipment> The items delivered to the person and not answered yet, oldest delivery first. */
    private function equipment(string $teleworkerId): array
    {
        return array_values(EquipmentItem::query()->where('teleworker_id', $teleworkerId)->where('status', EquipmentStatus::Pending)->orderBy('delivered_on')->orderBy('id')->get()
            ->map(fn (EquipmentItem $item): PendingEquipment => new PendingEquipment($item->id, $item->description, $item->kind->label(), $item->delivered_on->toDateString()))->all());
    }

    /** @return list<PendingDocument> The versions in force asked of the person and not confirmed yet, oldest request first. */
    private function documents(OpenedPortal $portal): array
    {
        $pending = Acknowledgement::query()->where('employee_id', $portal->employeeId)->whereNull('acknowledged_at')->select('document_id');

        return array_values(TeleworkDocument::query()->inForce()->whereIn('id', $pending)->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (TeleworkDocument $document): PendingDocument => new PendingDocument(
                $document->id,
                "{$document->kind->label()} · versión {$document->version}",
                $document->created_at->setTimezone(config()->string('app.display_timezone'))->toDateString(),
            ))->all());
    }
}
