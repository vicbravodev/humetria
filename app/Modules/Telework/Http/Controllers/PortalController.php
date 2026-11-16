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
use App\Modules\Telework\Data\PortalSectionLink;
use App\Modules\Telework\Exceptions\PortalUnavailable;
use App\Modules\Telework\Support\OpenedPortal;
use App\Modules\Telework\Support\PortalPending;
use App\Modules\Telework\Support\SessionPortal;
use App\Modules\Telework\Support\TeleworkWorkerSection;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use Illuminate\Container\Attributes\Tag;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The personal portal of a person. Its start by link (`portal/{token}`, the link's token is the credential) lists the tools
 * that still open with it; the telework section (`me/{slug}/telework`) is the person's page in their session.
 */
final class PortalController extends Controller
{
    /** The order of the tools in the start by link: the telework one first, then each of the others as they are listed. */
    private const array ORDER = ['telework', 'attendance', 'survey', 'engagement', 'vacations', 'team', 'seating', 'policy', 'climate', 'feedback', 'support', 'center', 'complaints'];

    /**
     * @param  iterable<PortalSection>  $tools  what each module offers by link, tagged `portal.sections`
     */
    public function show(string $token, OpenPortal $openPortal, TeleworkWorkerSection $telework, #[Tag('portal.sections')] iterable $tools): Response
    {
        try {
            $portal = $openPortal->handle($token);
        } catch (PortalUnavailable) {
            return Inertia::render('telework/portal', ['unavailable' => true, 'company' => null, 'name' => null, 'sections' => []]);
        }

        return Inertia::render('telework/portal', [
            'unavailable' => false,
            'company' => $portal->company,
            'name' => $portal->name,
            'sections' => $this->sections($portal, $token, $telework, $tools),
        ]);
    }

    /** The telework section of the person of the session: what waits for them and what they sent. */
    public function section(string $tenant, SessionPortal $portals, PortalPending $pending): Response
    {
        $portal = $portals->current() ?? abort(404);
        $items = $pending->for($portal);
        $row = fn (PendingChecklist $item): array => ['id' => $item->id, 'workplace' => $item->workplace, 'section' => $item->section, 'label' => $item->label];

        return Inertia::render('telework/portal-section', [
            'slug' => $tenant,
            'company' => $portal->company,
            'canNotify' => $portal->teleworkerId !== null,
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
     * The tools this person has, in a fixed order: each module answers for its own and says nothing when it has none. The
     * telework one goes to the person's page, where they enter first.
     *
     * @param  iterable<PortalSection>  $tools
     * @return list<array{key: string, label: string, description: string, href: string, pending: int|null}>
     */
    private function sections(OpenedPortal $portal, string $token, TeleworkWorkerSection $telework, iterable $tools): array
    {
        $identity = new PortalIdentity($portal->tenantId, $portal->employeeId, $portal->company, $portal->name);
        // Without NOM-037 there is no telework section to ask about.
        $own = $portal->telework ? $telework->for(new WorkerIdentity($portal->tenantId, $portal->employeeId, $portal->company, $portal->name)) : null;
        $links = $own === null ? [] : [new PortalSectionLink($own->key, $own->label, $own->description, $own->href, $own->pending)];

        foreach ($tools as $tool) {
            $link = $tool->for($identity, $token);

            if ($link !== null) {
                $links[] = $link;
            }
        }

        usort($links, fn (PortalSectionLink $a, PortalSectionLink $b): int => array_search($a->key, self::ORDER, true) <=> array_search($b->key, self::ORDER, true));

        return array_map(fn (PortalSectionLink $link): array => ['key' => $link->key, 'label' => $link->label, 'description' => $link->description, 'href' => $link->href, 'pending' => $link->pending], $links);
    }
}
