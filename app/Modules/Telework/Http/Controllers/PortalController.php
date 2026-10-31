<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\OpenPortal;
use App\Modules\Telework\Data\PendingChecklist;
use App\Modules\Telework\Data\PendingDocument;
use App\Modules\Telework\Data\PendingEquipment;
use App\Modules\Telework\Data\PendingPhotoRequest;
use App\Modules\Telework\Data\PendingVisit;
use App\Modules\Telework\Exceptions\PortalUnavailable;
use App\Modules\Telework\Support\PortalPending;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The personal portal of a person, reached from the emailed link. No account: the link's token is the credential.
 */
final class PortalController extends Controller
{
    public function show(string $token, OpenPortal $openPortal, PortalPending $pending): Response
    {
        try {
            $portal = $openPortal->handle($token);
        } catch (PortalUnavailable) {
            return Inertia::render('telework/portal', [
                'unavailable' => true,
                'token' => null,
                'company' => null,
                'name' => null,
                'pending' => ['checklists' => [], 'sent' => [], 'photos' => [], 'visits' => [], 'documents' => [], 'equipment' => []],
            ]);
        }

        $items = $pending->for($portal);
        $row = fn (PendingChecklist $item): array => ['id' => $item->id, 'workplace' => $item->workplace, 'section' => $item->section, 'label' => $item->label];

        return Inertia::render('telework/portal', [
            'unavailable' => false,
            'token' => $token,
            'company' => $portal->company,
            'name' => $portal->name,
            'pending' => [
                'checklists' => array_map($row, $items->checklists),
                'sent' => array_map($row, $items->sent),
                'photos' => array_map(fn (PendingPhotoRequest $item): array => ['id' => $item->id, 'workplace' => $item->workplace, 'note' => $item->note], $items->photos),
                'visits' => array_map(fn (PendingVisit $item): array => ['id' => $item->id, 'workplace' => $item->workplace], $items->visits),
                'documents' => array_map(fn (PendingDocument $item): array => ['id' => $item->id, 'title' => $item->title, 'publishedOn' => $item->publishedOn], $items->documents),
                'equipment' => array_map(fn (PendingEquipment $item): array => ['id' => $item->id, 'title' => $item->title, 'kind' => $item->kind, 'deliveredOn' => $item->deliveredOn], $items->equipment),
            ],
        ]);
    }
}
