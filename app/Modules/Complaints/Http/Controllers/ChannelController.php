<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Complaints\Actions\ActivateChannel;
use App\Modules\Complaints\Actions\PauseChannel;
use App\Modules\Complaints\Actions\ResumeChannel;
use App\Modules\Complaints\Actions\RotateAccessCode;
use App\Modules\Complaints\Actions\SaveChannelSettings;
use App\Modules\Complaints\Http\Requests\ChannelSettingsRequest;
use App\Modules\Complaints\Support\ChannelPages;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The company's channel for complaints: its state, its settings, and the actions that open it, pause it and change its code.
 * Only who manages the channel; what the cases say is another controller's, and never reaches this page.
 */
final class ChannelController extends Controller
{
    public function show(mixed $tenant, ChannelPages $pages): Response
    {
        Gate::authorize('manage-complaint-channel');

        return Inertia::render('complaints/channel', $pages->show());
    }

    public function activate(ChannelSettingsRequest $request, mixed $tenant, ActivateChannel $activate, #[CurrentUser] User $user): RedirectResponse
    {
        $activate->handle($request->settings(), $user->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'El canal quedó activo.']);

        return to_route('tenant.complaints.channel.show', $tenant);
    }

    public function update(ChannelSettingsRequest $request, mixed $tenant, SaveChannelSettings $save): RedirectResponse
    {
        $save->handle($request->settings());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ajustes guardados.']);

        return to_route('tenant.complaints.channel.show', $tenant);
    }

    public function pause(mixed $tenant, PauseChannel $pause): RedirectResponse
    {
        Gate::authorize('manage-complaint-channel');
        $pause->handle();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'El canal quedó en pausa.']);

        return to_route('tenant.complaints.channel.show', $tenant);
    }

    public function resume(mixed $tenant, ResumeChannel $resume): RedirectResponse
    {
        Gate::authorize('manage-complaint-channel');
        $resume->handle();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'El canal volvió a recibir quejas.']);

        return to_route('tenant.complaints.channel.show', $tenant);
    }

    public function rotate(mixed $tenant, RotateAccessCode $rotate): RedirectResponse
    {
        Gate::authorize('manage-complaint-channel');
        $rotate->handle();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'El enlace cambió. El cartel anterior ya no sirve: imprime el nuevo.']);

        return to_route('tenant.complaints.channel.show', $tenant);
    }
}
