<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Concerns\MatchesManyIds;
use App\Modules\WorkerAccess\Enums\LinkKind;
use Carbon\CarbonImmutable;
use Database\Factories\WorkerAccess\WorkerAccessLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A one-time link a person enters with (spec «Identidad única de la persona trabajadora»): `issued` by RH or `requested` by
 * the person. Only the hash of its random token is kept, and it never reaches a page, a log or the audit trail. It opens
 * once, before it expires and while nobody revoked it.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $token_hash
 * @property LinkKind $kind
 * @property int|null $issued_by
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'token_hash', 'kind', 'issued_by', 'expires_at', 'used_at', 'revoked_at'])]
#[Hidden(['token_hash'])]
#[UseFactory(WorkerAccessLinkFactory::class)]
final class WorkerAccessLink extends Model
{
    /** @use HasFactory<WorkerAccessLinkFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids, MatchesManyIds;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** The current company's link of `$token` while it may still open: not used, not revoked, within its time. */
    public static function findPending(string $token): ?self
    {
        return self::query()->where('token_hash', self::hashToken($token))->whereNull('used_at')->whereNull('revoked_at')
            ->where('expires_at', '>', CarbonImmutable::now())->first();
    }

    /**
     * Spends the link: one conditional UPDATE, so of two requests with the same link at once only one finds it pending.
     */
    public function spend(): bool
    {
        $now = CarbonImmutable::now();

        return self::query()->whereKey($this->id)->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', $now)
            ->update(['used_at' => $now]) === 1;
    }

    /**
     * The links RH issued to these people that were neither used nor revoked, the newest of each person first; expired ones
     * too (RH sees that a link no longer opens). One query.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, self> by person
     */
    public static function issuedWaiting(array $employeeIds): array
    {
        $links = [];
        foreach (self::query()->among('employee_id', $employeeIds)->where('kind', LinkKind::Issued)->whereNull('used_at')->whereNull('revoked_at')
            ->orderByDesc('created_at')->get(['employee_id', 'created_at', 'expires_at']) as $link) {
            $links[$link->employee_id] ??= $link;
        }

        return $links;
    }

    /** Whether the link still opens: within its time (it was neither used nor revoked). */
    public function open(): bool
    {
        return $this->expires_at->greaterThan(CarbonImmutable::now());
    }

    /** Revokes the links of a person that may still open; of one kind only, when it is given. */
    public static function revokePending(string $employeeId, ?LinkKind $kind = null): void
    {
        self::query()->where('employee_id', $employeeId)->whereNull('used_at')->whereNull('revoked_at')
            ->when($kind !== null, fn ($query) => $query->where('kind', $kind))
            ->update(['revoked_at' => CarbonImmutable::now()]);
    }

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return ['kind' => LinkKind::class, 'issued_by' => 'integer', 'expires_at' => 'immutable_datetime', 'used_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
