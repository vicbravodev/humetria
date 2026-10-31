<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\OpenPortal;
use App\Modules\Telework\Data\PendingChecklist;
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
                'pending' => ['checklists' => [], 'sent' => []],
            ]);
        }

        $items = $pending->for($portal);
        $row = fn (PendingChecklist $item): array => ['id' => $item->id, 'workplace' => $item->workplace, 'section' => $item->section, 'label' => $item->label];

        return Inertia::render('telework/portal', [
            'unavailable' => false,
            'token' => $token,
            'company' => $portal->company,
            'name' => $portal->name,
            'pending' => ['checklists' => array_map($row, $items->checklists), 'sent' => array_map($row, $items->sent)],
        ]);
    }
}
