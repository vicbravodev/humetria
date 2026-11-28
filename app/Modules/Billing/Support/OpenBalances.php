<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\Statement;
use Carbon\CarbonImmutable;

/**
 * The issued statements that still owe something, with what each owes (Statement::withBalance, the one balance) and how old it is. A statement is past due on the day the
 * `overdue` scope says so (issued `billing.overdue_after_days` ago): that is its first day past due, and from there it ages into
 * 1–30, 31–60 and more than 60 days. Before it, it is current.
 * ponytail: reads every open statement in memory; sum in SQL by bucket if open statements ever reach many thousands.
 */
final readonly class OpenBalances
{
    public const array BUCKETS = ['current', '1_30', '31_60', 'over_60']; // @pest-mutate-ignore: RemoveArrayItem

    /**
     * @param  list<string>|null  $accountIds  only these accounts; null for all of them.
     * @return list<array{account_id: string, balance: int, days: int}> `days` past due (1 the first day), or minus the days left to pay.
     */
    public function handle(?array $accountIds = null, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $statements = Statement::query()->withBalance()
            ->when($accountIds !== null, fn ($query) => $query->whereIn('billing_account_id', $accountIds ?? []))
            ->orderBy('issued_at')->orderBy('id')
            ->get();
        $open = [];

        foreach ($statements as $statement) {
            $open[] = [
                'account_id' => $statement->billing_account_id,
                'balance' => $statement->balance_cents,
                'days' => self::days($statement->issued_at ?? $now, $now),
            ];
        }

        return $open;
    }

    /** Days past due (1 on the first day), or minus the days left before it is. */
    public static function days(CarbonImmutable $issuedAt, CarbonImmutable $now): int
    {
        $seconds = $now->getTimestamp() - $issuedAt->addDays(config()->integer('billing.overdue_after_days'))->getTimestamp();

        return $seconds >= 0 ? intdiv($seconds, 86_400) + 1 : -intdiv(-$seconds + 86_399, 86_400);
    }

    public static function bucket(int $days): string
    {
        return match (true) { // @pest-mutate-ignore: TrueToFalse
            $days <= 0 => 'current',
            $days <= 30 => '1_30',
            $days <= 60 => '31_60',
            default => 'over_60',
        };
    }
}
