<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Actions\AssignAccountManager;
use App\Modules\Tenancy\Actions\ExtendTrials;
use App\Modules\Tenancy\Http\Requests\AssignAccountManagerRequest;
use App\Modules\Tenancy\Http\Requests\ExtendTrialsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** What the superadmin does at once with the companies chosen in the console's list. */
final class CompanySelectionController extends Controller
{
    public function assignManager(AssignAccountManagerRequest $request, AssignAccountManager $assign): RedirectResponse
    {
        $manager = $request->manager();
        $changed = $assign->handle($request->companies(), $manager);

        Inertia::flash('toast', ['type' => 'success', 'message' => $manager === null
            ? 'Quitaste el responsable a '.$this->companies($changed).'.'
            : $manager->name.' responde ahora por '.$this->companies($changed).'.']);

        return redirect()->route('companies.index');
    }

    public function extendTrials(ExtendTrialsRequest $request, ExtendTrials $extend): RedirectResponse
    {
        $extended = $extend->handle($request->companies(), $request->days());

        Inertia::flash('toast', ['type' => 'success', 'message' => $extended === 0
            ? 'Ninguna de las elegidas está en prueba: no cambió nada.'
            : 'Prueba extendida '.$request->days().' días a '.$this->companies($extended).'.']);

        return redirect()->route('companies.index');
    }

    private function companies(int $count): string
    {
        return $count === 1 ? '1 empresa' : $count.' empresas';
    }
}
