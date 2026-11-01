<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\UpdateTeleworkSettings;
use App\Modules\Telework\Enums\ReviewPeriod;
use App\Modules\Telework\Http\Requests\TeleworkSettingsRequest;
use App\Modules\Telework\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * How often the checklist's Section II is applied and the consent text (owner and RH only).
 */
final class TeleworkSettingsController extends Controller
{
    public function edit(Settings $settings): Response
    {
        Gate::authorize('manage-telework');
        $consent = $settings->currentConsent();

        return Inertia::render('telework/settings', [
            'periodMonths' => $settings->periodMonths(),
            'presets' => ReviewPeriod::presets(),
            'maxMonths' => ReviewPeriod::MAX,
            'consent' => ['version' => $consent->version, 'text' => $consent->text],
        ]);
    }

    public function update(TeleworkSettingsRequest $request, mixed $tenant, UpdateTeleworkSettings $update): RedirectResponse
    {
        // Every route of this controller sits behind the auth middleware: the check only narrows the type.
        abort_if($request->user() === null, 403); // @pest-mutate-ignore: DecrementInteger,IncrementInteger,RemoveFunctionCall

        $update->handle($request->periodMonths(), $request->consentText(), $request->user()->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ajustes de NOM-037 guardados.']);

        return redirect()->route('tenant.telework.settings.edit', $tenant);
    }
}
