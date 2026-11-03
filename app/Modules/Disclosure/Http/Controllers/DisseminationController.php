<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Disclosure\Actions\RecordDissemination;
use App\Modules\Disclosure\Actions\RecordManualAcknowledgments;
use App\Modules\Disclosure\Actions\RemindPending;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Http\Requests\DisseminationRecordRequest;
use App\Modules\Disclosure\Http\Requests\ManualAcknowledgmentsRequest;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Support\AckProgress;
use App\Modules\Disclosure\Support\DisseminationPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The follow-up of the disclosure (criterio C-53): who confirmed the policy and who did not, the paper acknowledgments RH
 * records in bulk and the reminder to whoever is pending. Owner and RH only: it names people. A version of another company, a
 * draft or one that does not exist is not found.
 */
final class DisseminationController extends Controller
{
    private const array STATUSES = ['all', 'pending', 'confirmed'];

    public function index(Request $request, DisseminationPages $pages): Response
    {
        Gate::authorize('manage-disclosure');
        $version = $request->query('version');
        $status = $request->string('status')->toString();

        abort_if(is_string($version) && ! Str::isUuid($version), 404);

        return Inertia::render('disclosure/dissemination', $pages->show(
            is_string($version) ? $version : null,
            in_array($status, self::STATUSES, true) ? $status : 'all',
            $request->string('q')->trim()->toString(),
        ));
    }

    public function record(ManualAcknowledgmentsRequest $request, mixed $tenant, string $document, RecordManualAcknowledgments $record, AckProgress $progress): RedirectResponse
    {
        $found = Document::query()->where('kind', DocumentKind::Policy)->where('status', DocumentStatus::Published)->findOrFail($document);
        $ids = $request->allMatching() ? $progress->pendingMatching($found, $request->search()) : $request->employeeIds();
        $count = $record->handle($found->id, $ids, $request->day(), $request->note(), $request->userId());

        if ($count === 0) {
            throw ValidationException::withMessages(['employee_ids' => 'Nadie quedó registrado: ya confirmaron, salieron o tienen una versión más reciente.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $count === 1 ? 'Se registró 1 acuse en papel.' : "Se registraron {$count} acuses en papel."]);

        return redirect()->route('tenant.disclosure.dissemination', $tenant);
    }

    public function remind(Request $request, mixed $tenant, string $document, RemindPending $remind): RedirectResponse
    {
        Gate::authorize('manage-disclosure');
        $count = $remind->handle($document);
        Inertia::flash('toast', ['type' => 'success', 'message' => $count === 1 ? 'Se recordará a 1 persona.' : "Se recordará a {$count} personas."]);

        return redirect()->route('tenant.disclosure.dissemination', $tenant);
    }

    public function storeRecord(DisseminationRecordRequest $request, mixed $tenant, RecordDissemination $record): RedirectResponse
    {
        $record->handle($request->string('work_center_id')->toString(), $request->string('medium')->toString(), $request->items(), $request->string('done_on')->toString(), $request->string('note')->toString(), $request->userId());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Medio registrado.']);

        return redirect()->route('tenant.disclosure.dissemination', $tenant);
    }
}
