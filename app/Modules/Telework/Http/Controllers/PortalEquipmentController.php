<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\RespondEquipment;
use App\Modules\Telework\Support\SessionPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The person's answer about an item of equipment, from their portal: received, or refused with the reason. An item
 * that was not delivered to them, or a link that opens nothing, does not exist.
 */
final class PortalEquipmentController extends Controller
{
    public function respond(string $tenant, string $item, Request $request, SessionPortal $portals, RespondEquipment $respond): RedirectResponse
    {
        $portal = $portals->current() ?? abort(404);
        $request->validate(['received' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $respond->handle($portal, $item, $request->boolean('received'), $request->string('reason')->toString(), $request->ip());

        return to_route('portal.telework.show', $tenant);
    }
}
