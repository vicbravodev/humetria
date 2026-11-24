<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Contracts\CompanyPlans;
use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Billing\Contracts\WhatsAppQuota;
use App\Modules\Tenancy\Actions\UpdateCompanyChannels;
use App\Modules\Tenancy\Contracts\CompanyChannels;
use App\Modules\Tenancy\Data\ChannelSetting;
use App\Modules\Tenancy\Http\Requests\UpdateCompanyChannelsRequest;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** «Canales de comunicación»: through which channels the company's people are written to. The owner's. */
final class CompanyChannelsController extends Controller
{
    public function edit(Tenant $tenant, CompanyChannels $channels, CompanyPlans $plans, WhatsAppQuota $quota, UsageMeter $usage): Response
    {
        Gate::authorize('manage-members');

        $snapshot = $usage->snapshot($tenant->id, UsageMeter::WHATSAPP);

        return Inertia::render('tenancy/channels', [
            'channels' => array_map(fn (ChannelSetting $setting): array => [
                'channel' => $setting->channel,
                'enabled' => $setting->enabled,
                'preference' => $setting->preference,
                'monthlyCap' => $setting->monthlyCap,
                'consentVersion' => $setting->consentVersion,
            ], $channels->settings($tenant->id)),
            'whatsAppAvailable' => config()->boolean('whatsapp.enabled'),
            'hasPlan' => $plans->current($tenant->id) !== null,
            'consent' => [
                'version' => config()->string('whatsapp.consent.version'),
                'text' => str_replace('{empresa}', $tenant->trade_name, config()->string('whatsapp.consent.text')),
            ],
            'costNotice' => str_replace('{precio}', $quota->price()->label(), config()->string('whatsapp.cost_notice')),
            'whatsAppQuota' => ['used' => $snapshot->used, 'quota' => $snapshot->quota],
        ]);
    }

    public function update(UpdateCompanyChannelsRequest $request, Tenant $tenant, UpdateCompanyChannels $update): RedirectResponse
    {
        $update->handle($tenant->id, $request->toData(), $request->user()?->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Guardamos los canales de comunicación.']);

        return back();
    }
}
