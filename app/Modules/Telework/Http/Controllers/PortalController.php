<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\OpenPortal;
use App\Modules\Telework\Contracts\PortalSection;
use App\Modules\Telework\Data\PendingChecklist;
use App\Modules\Telework\Data\PendingDocument;
use App\Modules\Telework\Data\PendingEquipment;
use App\Modules\Telework\Data\PendingPhotoRequest;
use App\Modules\Telework\Data\PendingVisit;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Data\PortalPendingData;
use App\Modules\Telework\Data\PortalSectionLink;
use App\Modules\Telework\Exceptions\PortalUnavailable;
use App\Modules\Telework\Support\OpenedPortal;
use App\Modules\Telework\Support\PortalPending;
use Illuminate\Container\Attributes\Tag;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The personal portal of a person, reached from the emailed link. No account: the link's token is the credential.
 */
final class PortalController extends Controller
{
    /** The order of the tools in the home: the telework one first, then each of the others as they are listed. */
    private const array ORDER = ['attendance', 'survey', 'engagement', 'vacations', 'team', 'seating', 'policy', 'climate', 'feedback', 'support', 'center', 'complaints'];

    /**
     * @param  iterable<PortalSection>  $tools  what each module offers, tagged `portal.sections`
     */
    public function show(string $token, OpenPortal $openPortal, PortalPending $pending, #[Tag('portal.sections')] iterable $tools): Response
    {
        try {
            $portal = $openPortal->handle($token);
        } catch (PortalUnavailable) {
            return Inertia::render('telework/portal', [
                'unavailable' => true,
                'token' => null,
                'company' => null,
                'name' => null,
                'canNotify' => false,
                'sections' => [],
                'pending' => ['checklists' => [], 'sent' => [], 'photos' => [], 'visits' => [], 'documents' => [], 'equipment' => []],
            ]);
        }

        // Without NOM-037 there is nothing of this tool to show; the person's other tools are in `sections`.
        $items = $portal->telework ? $pending->for($portal) : new PortalPendingData([], [], [], [], []);
        $row = fn (PendingChecklist $item): array => ['id' => $item->id, 'workplace' => $item->workplace, 'section' => $item->section, 'label' => $item->label];

        return Inertia::render('telework/portal', [
            'unavailable' => false,
            'token' => $token,
            'company' => $portal->company,
            'name' => $portal->name,
            'canNotify' => $portal->telework && $portal->teleworkerId !== null,
            'sections' => $this->sections($portal, $token, $items, $tools),
            // Each row names the fields of its DTO, which has exactly those: sending the DTOs would send the same JSON.
            'pending' => [
                'checklists' => array_map($row, $items->checklists), // @pest-mutate-ignore: UnwrapArrayMap
                'sent' => array_map($row, $items->sent), // @pest-mutate-ignore: UnwrapArrayMap
                'photos' => array_map(fn (PendingPhotoRequest $item): array => ['id' => $item->id, 'workplace' => $item->workplace, 'note' => $item->note], $items->photos), // @pest-mutate-ignore: UnwrapArrayMap
                'visits' => array_map(fn (PendingVisit $item): array => ['id' => $item->id, 'workplace' => $item->workplace], $items->visits), // @pest-mutate-ignore: UnwrapArrayMap
                'documents' => array_map(fn (PendingDocument $item): array => ['id' => $item->id, 'title' => $item->title, 'publishedOn' => $item->publishedOn], $items->documents), // @pest-mutate-ignore: UnwrapArrayMap
                'equipment' => array_map(fn (PendingEquipment $item): array => ['id' => $item->id, 'title' => $item->title, 'kind' => $item->kind, 'deliveredOn' => $item->deliveredOn], $items->equipment), // @pest-mutate-ignore: UnwrapArrayMap
            ],
        ]);
    }

    /**
     * The tools this person has, in a fixed order: each module answers for its own and says nothing when it has none.
     *
     * @param  iterable<PortalSection>  $tools
     * @return list<array{key: string, label: string, description: string, href: string, pending: int|null}>
     */
    private function sections(OpenedPortal $portal, string $token, PortalPendingData $items, iterable $tools): array
    {
        $identity = new PortalIdentity($portal->tenantId, $portal->employeeId, $portal->company, $portal->name);
        $links = [];

        if ($portal->telework && $portal->teleworkerId !== null) {
            $waiting = count($items->checklists) + count($items->photos) + count($items->visits) + count($items->documents) + count($items->equipment);
            $links[] = new PortalSectionLink('telework', 'Su teletrabajo', 'Sus pendientes y sus avisos: cambio de domicilio, riesgo de trabajo o alteración de sus condiciones.', route('portal.notices.show', $token), $waiting === 0 ? null : $waiting);
        }

        foreach ($tools as $tool) {
            $link = $tool->for($identity, $token);

            if ($link !== null) {
                $links[] = $link;
            }
        }

        usort($links, fn (PortalSectionLink $a, PortalSectionLink $b): int => array_search($a->key, ['telework', ...self::ORDER], true) <=> array_search($b->key, ['telework', ...self::ORDER], true));

        return array_map(fn (PortalSectionLink $link): array => ['key' => $link->key, 'label' => $link->label, 'description' => $link->description, 'href' => $link->href, 'pending' => $link->pending], $links);
    }
}
