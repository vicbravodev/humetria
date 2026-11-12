import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { newPeriodSoon } from '@/lib/leave/balances';
import { shortDate } from '@/lib/leave/format';
import { index as employees } from '@/routes/tenant/employees';
import type { LeaveBalanceRow } from '@/types';

/** When the next period starts, with a pill when it is close; with no start date, the way to capture it. */
export default function BalanceNext({
    person,
    today,
}: {
    person: LeaveBalanceRow;
    today: string;
}) {
    const tenantSlug = useTenantSlug();
    const soon = newPeriodSoon(person.nextAnniversary, today);

    if (person.nextAnniversary === null) {
        return (
            <Button variant="outline" size="sm" asChild>
                <Link
                    href={employees.url(tenantSlug, {
                        query: { q: person.name },
                    })}
                >
                    Capturar la fecha
                </Link>
            </Button>
        );
    }

    return (
        <div className="flex flex-col items-start gap-1">
            <span className="tabular-nums">
                {shortDate(person.nextAnniversary)}
            </span>
            {soon !== null && <Badge variant="warning">{soon}</Badge>}
        </div>
    );
}
