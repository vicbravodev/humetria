<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Contracts\ChannelConsent;
use App\Modules\Messaging\Contracts\ChannelPicker;
use App\Modules\Messaging\Models\OptOut;
use App\Modules\Tenancy\Contracts\CompanyChannels;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\Tenancy\Data\ChannelSetting;
use App\Support\PhoneNumber;

/**
 * Rules 1, 2, 4 and 5 of the design: which channel wins (in the demo, every channel that works) and whether WhatsApp may write to a phone. `canSend` always reads fresh (a
 * job runs it just before sending); `route` loads the company's settings and opt-outs once and compares in memory, because a
 * launch asks it for every person.
 */
final class ChannelRules implements ChannelConsent, ChannelPicker
{
    /** @var array<string, array{settings: list<ChannelSetting>, whatsAppOpen: bool, demo: bool, optOuts: array<string, true>}> */
    private array $routing = [];

    public function __construct(
        private readonly CompanyChannels $channels,
        private readonly CurrentTenant $current,
        private readonly DemoCompanies $demo,
        private readonly WhatsAppSwitch $switch,
        private readonly RecipientHash $hashes,
        private readonly PhoneForMeta $meta,
    ) {}

    public function canSend(string $tenantId, string $channel, string $phone): bool
    {
        $settings = $this->channels->settings($tenantId);

        if ($channel !== 'whatsapp') {
            return $this->setting($settings, $channel)?->enabled === true;
        }

        $e164 = PhoneNumber::e164($phone);

        if ($e164 === null || ! $this->whatsAppOpen($tenantId, $settings)) {
            return false;
        }

        return ! OptOut::query()
            ->whereIn('recipient_hash', $this->hashes->all($e164))
            ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->exists();
    }

    public function route(?string $email, ?string $mobile, bool $whatsAppAllowed = true): array
    {
        $hasEmail = $email !== null && $email !== '';

        // The function does not exist in this deployment: mail as it always was, with no query.
        if (! config()->boolean('whatsapp.enabled')) {
            return $hasEmail ? ['mail' => $email] : [];
        }

        $tenantId = $this->current->id();
        $routing = $this->routing[$tenantId] ??= $this->load($tenantId);
        $routes = [];

        foreach ($routing['settings'] as $setting) {
            if (! $setting->enabled) {
                continue;
            }

            if ($setting->channel === 'mail' && $hasEmail) {
                $routes['mail'] = $email;
            }

            if ($setting->channel === 'whatsapp' && $whatsAppAllowed && $routing['whatsAppOpen'] && $this->reachable($mobile, $routing['optOuts'])) {
                $routes['whatsapp'] = (string) $mobile;
            }

            // A real company writes through the first channel that works; the demo through every one, so its audience gets both.
            if ($routes !== [] && ! $routing['demo']) {
                return $routes;
            }
        }

        return $routes;
    }

    /**
     * @return array{settings: list<ChannelSetting>, whatsAppOpen: bool, demo: bool, optOuts: array<string, true>}
     */
    private function load(string $tenantId): array
    {
        $optOuts = [];

        $rows = OptOut::query()->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))->get();

        foreach ($rows as $row) {
            $optOuts[$row->recipient_hash] = true;
        }

        $settings = $this->channels->settings($tenantId);

        return ['settings' => $settings, 'whatsAppOpen' => $this->whatsAppOpen($tenantId, $settings), 'demo' => $this->demo->isDemo($tenantId), 'optOuts' => $optOuts];
    }

    /** @param array<string, true> $optOuts */
    private function reachable(?string $mobile, array $optOuts): bool
    {
        if ($this->meta->toMeta($mobile) === null) {
            return false;
        }

        $e164 = PhoneNumber::e164($mobile);

        if ($e164 === null) {
            return false;
        }

        foreach ($this->hashes->all($e164) as $hash) {
            if (isset($optOuts[$hash])) {
                return false;
            }
        }

        return true;
    }

    /**
     * The company-level part of the WhatsApp rule: the function is available and the owner has the channel on with the current
     * words accepted. The demo too (decision 5 of the guided demo): its seeded people have no phone, only the audience added live.
     *
     * @param  list<ChannelSetting>  $settings
     */
    private function whatsAppOpen(string $tenantId, array $settings): bool
    {
        $whatsApp = $this->setting($settings, 'whatsapp');

        return $whatsApp !== null
            && $whatsApp->enabled
            && $whatsApp->consentVersion === config()->string('whatsapp.consent.version')
            && $this->switch->available();
    }

    /** @param list<ChannelSetting> $settings */
    private function setting(array $settings, string $channel): ?ChannelSetting
    {
        foreach ($settings as $setting) {
            if ($setting->channel === $channel) {
                return $setting;
            }
        }

        return null;
    }
}
