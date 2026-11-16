<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Actions;

use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Enums\LinkKind;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * A new one-time link of a person of the current company, as the address the person opens. An `issued` one (RH's, 48 hours)
 * revokes every link of the person that still opens; a `requested` one (the person's, 30 minutes) revokes the requested ones.
 * The token is random and exists in clear only in the address: only its hash is kept, and it never reaches a log.
 */
final readonly class CreateAccessLink
{
    public function __construct(private Companies $companies) {}

    /** @return array{0: WorkerAccessLink, 1: string} the link and its address */
    public function handle(string $employeeId, LinkKind $kind, ?int $issuedBy = null): array
    {
        $token = Str::random(48);
        $now = CarbonImmutable::now();
        $company = $this->companies->current();

        $link = DB::transaction(function () use ($employeeId, $kind, $issuedBy, $token, $now, $company): WorkerAccessLink {
            // One person's links one at a time, so two at once do not both stay open.
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["worker_access_links:{$employeeId}"]);
            WorkerAccessLink::revokePending($employeeId, $kind === LinkKind::Issued ? null : LinkKind::Requested);

            return WorkerAccessLink::query()->create([
                'tenant_id' => $company->id,
                'employee_id' => $employeeId,
                'token_hash' => WorkerAccessLink::hashToken($token),
                'kind' => $kind,
                'issued_by' => $issuedBy,
                'expires_at' => $kind === LinkKind::Issued
                    ? $now->addHours(config()->integer('worker_access.links.issued_hours'))
                    : $now->addMinutes(config()->integer('worker_access.links.requested_minutes')),
            ]);
        });

        Log::info('worker_access.link_created', ['link_id' => $link->id, 'employee_id' => $employeeId, 'kind' => $kind->value]);

        return [$link, rtrim(config()->string('app.url'), '/').route('worker.link.show', [$company->slug, $token], absolute: false)];
    }
}
