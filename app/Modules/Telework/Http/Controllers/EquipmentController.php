<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\RegisterEquipment;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Http\Requests\EquipmentRequest;
use App\Modules\Telework\Models\Teleworker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * RH registers the equipment delivered to a person. A period that ended, or one outside the company, is a 404.
 * There is no edit or delete: the person answers each item in their portal.
 */
final class EquipmentController extends Controller
{
    public function store(EquipmentRequest $request, mixed $tenant, string $teleworker, RegisterEquipment $register): RedirectResponse
    {
        $period = Str::isUuid($teleworker) ? Teleworker::query()->find($teleworker) : null;
        abort_if($period === null || $period->status === TeleworkerStatus::Ended || $request->user() === null, 404);

        $register->handle($period, $request->kind(), $request->string('description')->toString(), $request->deliveredOn(), $request->user()->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipo registrado. La persona lo confirma desde su portal.']);

        return redirect()->route('tenant.telework.teleworkers.show', [$tenant, $period->id]);
    }
}
