<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Tenancy\Contracts\WorkCenters;

/**
 * What a person's policy page receives: the version in force in their work center, with the company name in place, and
 * where their own confirmation stands. Nothing about anyone else.
 */
final readonly class PortalPolicyPages
{
    public function __construct(private Roster $roster, private PolicyStanding $standing, private WorkCenters $workCenters) {}

    /**
     * @return array<string, mixed>
     */
    public function show(PortalIdentity $person, string $token): array
    {
        $center = $this->roster->find($person->employeeId)->workCenterId ?? '';
        $fact = $this->standing->inForce([$center])[$center] ?? null;
        $document = $fact === null ? null : Document::query()->find($fact->documentId);
        $ack = $fact === null ? null : Acknowledgment::query()->where('document_id', $fact->documentId)->where('employee_id', $person->employeeId)->first();

        return [
            'token' => $token,
            'company' => $person->company,
            'name' => $person->name,
            'policy' => $fact === null || $document === null ? null : [
                'version' => $fact->version,
                'publishedOn' => Days::moment($fact->publishedAt),
                'sections' => PolicyText::sections($document->content, $this->workCenters->profile($center)->legalName ?? $person->company),
            ],
            'acknowledgment' => $ack === null ? null : [
                'documentId' => $ack->document_id,
                'state' => $ack->acknowledged_at === null ? 'pending' : 'confirmed',
                'on' => $ack->acknowledged_at === null ? null : Days::moment($ack->acknowledged_at),
            ],
        ];
    }
}
