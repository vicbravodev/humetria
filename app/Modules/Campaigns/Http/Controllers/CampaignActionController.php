<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Actions\CloseCampaign;
use App\Modules\Campaigns\Actions\DrawReplacements;
use App\Modules\Campaigns\Actions\DrawSample;
use App\Modules\Campaigns\Actions\ExtendCampaign;
use App\Modules\Campaigns\Actions\InviteMissing;
use App\Modules\Campaigns\Actions\LaunchCampaign;
use App\Modules\Campaigns\Actions\ResendInvitations;
use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Http\Requests\DeadlineRequest;
use App\Modules\Campaigns\Http\Requests\ReplacementsRequest;
use App\Modules\Campaigns\Models\Participant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * What RH does to one evaluation: draw its sample again, launch it, and later follow it up and close it.
 */
final class CampaignActionController extends Controller
{
    public function redraw(mixed $tenant, string $campaign, DrawSample $drawSample): RedirectResponse
    {
        Gate::authorize('manage-campaigns');
        $draft = CampaignController::find($campaign, CampaignStatus::Draft);
        abort_if($draft->population !== Population::Sample, 409);

        $drawSample->handle($draft);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Muestra sorteada de nuevo.']);

        return redirect()->route('tenant.campaigns.show', [$tenant, $draft->id]);
    }

    public function launch(DeadlineRequest $request, mixed $tenant, string $campaign, LaunchCampaign $launchCampaign): RedirectResponse
    {
        Gate::authorize('manage-campaigns');
        $draft = CampaignController::find($campaign, CampaignStatus::Draft);

        $launchCampaign->handle($draft, $request->closesOn());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evaluación lanzada. Las invitaciones van en camino.']);

        return redirect()->route('tenant.campaigns.show', [$tenant, $draft->id]);
    }

    public function resend(mixed $tenant, string $campaign, ResendInvitations $resendInvitations): RedirectResponse
    {
        Gate::authorize('manage-campaigns');
        $sent = $resendInvitations->handle(CampaignController::find($campaign, CampaignStatus::Active));

        return $this->done($sent, 'No hay a quién reenviar: nadie con correo tiene guías pendientes.', 'Enlace reenviado a 1 persona.', "Enlace reenviado a {$sent} personas.");
    }

    public function resendOne(mixed $tenant, string $campaign, string $participant, ResendInvitations $resendInvitations): RedirectResponse
    {
        Gate::authorize('manage-campaigns');
        $active = CampaignController::find($campaign, CampaignStatus::Active);
        $person = Participant::query()->where('campaign_id', $active->id)->findOrFail($participant);

        // Nothing pending, or no email to send it to.
        abort_if($resendInvitations->handle($active, $person) === 0, 409);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Enlace reenviado.']);

        return back();
    }

    public function inviteMissing(mixed $tenant, string $campaign, InviteMissing $inviteMissing): RedirectResponse
    {
        Gate::authorize('manage-campaigns');
        $active = CampaignController::find($campaign, CampaignStatus::Active);
        abort_if($active->kind !== CampaignKind::Nom035Evaluation, 409);
        $sent = $inviteMissing->handle($active);

        return $this->done($sent, 'No falta nadie por invitar.', 'Se invitó a 1 persona.', "Se invitó a {$sent} personas.");
    }

    public function replacements(ReplacementsRequest $request, mixed $tenant, string $campaign, DrawReplacements $drawReplacements): RedirectResponse
    {
        Gate::authorize('manage-campaigns');
        $active = CampaignController::find($campaign, CampaignStatus::Active);
        abort_if($active->population !== Population::Sample, 409);
        $count = $drawReplacements->handle($active, $request->count());

        return $this->done($count, '', 'Se sorteó 1 persona más para la muestra.', "Se sortearon {$count} personas más para la muestra.");
    }

    public function extend(DeadlineRequest $request, mixed $tenant, string $campaign, ExtendCampaign $extendCampaign): RedirectResponse
    {
        Gate::authorize('manage-campaigns');
        $extendCampaign->handle(CampaignController::find($campaign, CampaignStatus::Active), $request->closesOn());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fecha de cierre actualizada.']);

        return back();
    }

    public function close(mixed $tenant, string $campaign, CloseCampaign $closeCampaign): RedirectResponse
    {
        Gate::authorize('manage-campaigns');
        $active = CampaignController::find($campaign, CampaignStatus::Active);
        $closeCampaign->handle($active);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evaluación cerrada. Los enlaces ya no funcionan.']);

        return redirect()->route('tenant.campaigns.show', [$tenant, $active->id]);
    }

    private function done(int $count, string $none, string $one, string $many): RedirectResponse
    {
        Inertia::flash('toast', match ($count) {
            0 => ['type' => 'info', 'message' => $none],
            1 => ['type' => 'success', 'message' => $one],
            default => ['type' => 'success', 'message' => $many],
        });

        return back();
    }
}
