<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Modules\Disclosure\Enums\PageLinkStatus;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Disclosure\Notifications\DisclosureDigest;
use App\Modules\Disclosure\Support\Board;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * The Monday email to each company's owner about its policy and disclosure (criteria C-50 and C-54): centers without a published
 * policy, centers whose latest closed evaluation is not published to the people (num. 7.8, which is an obligation) and the link of
 * the page about to expire. Counts only; nothing is sent when everything is in order. Only companies that started with the
 * disclosure (they have a text or a link) are told: a company that never opened it is not nagged. A blocked company, one with no
 * owner or one that fails never keeps the others' emails from going out.
 */
final readonly class SendDisclosureDigests
{
    public function __construct(private Companies $companies, private WorkCenters $workCenters, private Board $board) {}

    /**
     * @return int how many emails were sent.
     */
    public function handle(): int
    {
        $sent = 0;
        $tenants = array_values(array_unique([
            ...array_filter(PageLink::query()->withoutGlobalScopes()->pluck('tenant_id')->all(), is_string(...)),
            ...array_filter(Document::query()->withoutGlobalScopes()->pluck('tenant_id')->all(), is_string(...)),
        ]));
        sort($tenants);

        foreach ($tenants as $tenantId) {
            try {
                // Each in its own company, as the system.
                Context::scope(function () use ($tenantId, &$sent): void {
                    $company = $this->companies->enter($tenantId);

                    if (! $company->blocked) {
                        $sent += $this->company($company);
                    }
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others' emails from going out.
                report($failure);
            }
        }

        Log::info('disclosure.digests_sent', ['sent' => $sent]);

        return $sent;
    }

    private function company(CompanySummary $company): int
    {
        $owner = $this->companies->contact();
        $centers = array_values(array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived));
        $facts = $this->board->facts($centers);
        $withoutPolicy = count(array_filter($facts, fn ($fact): bool => $fact->policy === null));
        $unpublished = count(array_filter($facts, fn ($fact): bool => $fact->results !== null && $fact->results->applies && $fact->results->latest !== null && ! $fact->results->latestPublished));
        $link = PageLink::query()->first();
        $lines = [];

        if ($withoutPolicy > 0) {
            $lines[] = trans_choice('{1} :count centro sin política publicada.|[2,*] :count centros sin política publicada.', $withoutPolicy);
        }

        if ($unpublished > 0) {
            $lines[] = trans_choice('{1} :count centro con resultados sin publicar a las personas.|[2,*] :count centros con resultados sin publicar a las personas.', $unpublished);
        }

        if ($link !== null && $link->status() === PageLinkStatus::Active && $link->expires_at !== null) {
            $days = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay()->diffInDays($link->expires_at->setTimezone(config()->string('app.display_timezone'))->startOfDay());

            if ($days <= config()->integer('disclosure.link_expiry_warning_days')) {
                $lines[] = $days <= 1 ? 'El enlace de la página vence mañana.' : "El enlace de la página vence en {$days} días.";
            }
        }

        // Who of the people of the centers with a policy confirmed it (board V4-Correos-2).
        $asked = array_values(array_filter(array_map(fn ($fact): ?array => $fact->acknowledgments, $facts)));
        $total = array_sum(array_column($asked, 'active'));
        $confirmed = array_sum(array_column($asked, 'confirmed'));
        $confirmations = ['confirmed' => $confirmed, 'total' => $total, 'pending' => $total - $confirmed];

        if (($lines === [] && $confirmations['pending'] === 0) || $owner === null) {
            return 0;
        }

        Notification::route('mail', $owner)->notify(new DisclosureDigest($company, $lines, route('tenant.disclosure.index', $company->slug), $confirmations, route('tenant.disclosure.dissemination', $company->slug)));

        return 1;
    }
}
