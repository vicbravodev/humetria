<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use App\Modules\Billing\Contracts\CompanyPlans;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantNote;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What a company's page in the console shows besides its modules and limits: who owns it and who answers for it, the plan as
 * Billing tells it (words and dates, never money), its members, open invitations and the staff's notes, each a page at a time.
 */
final readonly class CompanyConsolePage
{
    private const int PER_PAGE = 20;

    public function __construct(private CompanyPlans $plans, private CompanyRoles $roles) {}

    /**
     * @return array{owner: array{name: string, email: string}|null, account_manager: array{id: int, name: string}|null, created_on: string}
     */
    public function contact(Tenant $tenant): array
    {
        $tenant->loadMissing(['ownerMembership.user', 'accountManager']);
        $owner = $tenant->ownerMembership?->user;
        $manager = $tenant->accountManager;

        return [
            'owner' => $owner === null ? null : ['name' => $owner->name, 'email' => $owner->email],
            'account_manager' => $manager === null ? null : ['id' => $manager->id, 'name' => $manager->name],
            'created_on' => DisplayDate::day($tenant->created_at),
        ];
    }

    /**
     * @return array{name: string, since: string, renews_on: string}|null
     */
    public function plan(Tenant $tenant): ?array
    {
        $plan = $this->plans->current($tenant->id);

        return $plan === null ? null : ['name' => $plan->name, 'since' => $this->date($plan->since), 'renews_on' => $this->date($plan->renewsOn)];
    }

    /**
     * @return LengthAwarePaginator<int, array{id: int, name: string, email: string, role: string|null, last_access: string|null}>
     */
    public function members(Tenant $tenant): LengthAwarePaginator
    {
        $roles = $this->roles->inCompany($tenant);

        return User::query()->join('tenant_memberships', 'tenant_memberships.user_id', '=', 'users.id')
            ->where('tenant_memberships.tenant_id', $tenant->id)->select('users.*')->orderBy('tenant_memberships.id')
            ->paginate(self::PER_PAGE, pageName: 'members_page')->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => ($roles[$user->id] ?? null)?->label,
                'last_access' => $user->last_login_at === null ? null : DisplayDate::day($user->last_login_at),
            ]);
    }

    /**
     * @return LengthAwarePaginator<int, array{id: string, name: string, email: string, status_label: string, expires_on: string}>
     */
    public function invitations(Tenant $tenant): LengthAwarePaginator
    {
        return TenantInvitation::query()->where('tenant_id', $tenant->id)->open()->latest()->orderByDesc('id')
            ->paginate(self::PER_PAGE, pageName: 'invitations_page')->withQueryString()
            ->through(fn (TenantInvitation $invitation): array => [
                'id' => $invitation->id,
                'name' => $invitation->name,
                'email' => $invitation->email,
                'status_label' => $invitation->status()->label(),
                'expires_on' => DisplayDate::day($invitation->expires_at),
            ]);
    }

    /**
     * @return LengthAwarePaginator<int, array{id: string, body: string, author: string|null, at: string}>
     */
    public function notes(Tenant $tenant): LengthAwarePaginator
    {
        return TenantNote::query()->where('tenant_id', $tenant->id)->with('author')->latest()->orderByDesc('id')
            ->paginate(self::PER_PAGE, pageName: 'notes_page')->withQueryString()
            ->through(fn (TenantNote $note): array => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->author?->name,
                'at' => DisplayDate::day($note->created_at),
            ]);
    }

    /** A calendar date as it was written, with no time zone to move it a day. */
    private function date(CarbonInterface $date): string
    {
        return str_replace('.', '', $date->isoFormat('D MMM YYYY'));
    }
}
