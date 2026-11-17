<?php

declare(strict_types=1);

namespace App\Modules\Sso\Actions;

use App\Modules\Sso\Models\SsoDomain;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Illuminate\Support\Str;

/**
 * The company a work email belongs to for single sign-on: the one that proved the domain after the @, and can use it today.
 */
final readonly class FindSsoCompany
{
    public function __construct(private CompanyRegistry $companies, private OpenSsoSignIn $open) {}

    /** The slug of the company's address, or null. */
    public function handle(string $email): ?string
    {
        $domain = Str::lower(Str::afterLast($email, '@'));
        $tenantId = $domain === '' || ! str_contains($email, '@') ? null : SsoDomain::query()->where('domain', $domain)->whereNotNull('verified_at')->value('tenant_id');

        if (! is_string($tenantId) || $this->open->handle($tenantId) === null) {
            return null;
        }

        return $this->companies->find($tenantId)?->slug;
    }
}
