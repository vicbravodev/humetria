<?php

declare(strict_types=1);

namespace App\Modules\Sso\Actions;

use App\Actions\RecordAudit;
use App\Modules\Sso\Contracts\DomainOwnership;
use App\Modules\Sso\Models\SsoDomain;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Proves a domain is the company's: its DNS carries the record, and no other company proved it before. False says it did not.
 */
final readonly class VerifySsoDomain
{
    public function __construct(private DomainOwnership $dns, private RecordAudit $audit) {}

    public function handle(SsoDomain $domain): bool
    {
        if ($domain->verified_at !== null) {
            return true;
        }

        $taken = SsoDomain::query()->where('domain', $domain->domain)->whereNotNull('verified_at')->exists();

        if ($taken || ! $this->dns->proves($domain->domain, $domain->token)) {
            Log::info('sso.domain_verification_failed', ['tenant_id' => $domain->tenant_id, 'domain' => $domain->domain, 'taken' => $taken]);

            return false;
        }

        try {
            // Its own transaction (a savepoint inside another): the violation must not abort the caller's.
            DB::transaction(fn (): bool => $domain->update(['verified_at' => now()]));
        } catch (UniqueConstraintViolationException) {
            // Another company proved it in the same instant: the database keeps one.
            return false;
        }

        Log::info('sso.domain_verified', ['tenant_id' => $domain->tenant_id, 'domain' => $domain->domain]);
        $this->audit->handle('sso.domain_verified', null, ['tenant_id' => $domain->tenant_id, 'domain' => $domain->domain]);

        return true;
    }
}
