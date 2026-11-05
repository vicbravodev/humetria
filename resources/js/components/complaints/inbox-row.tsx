import { Link } from '@inertiajs/react';
import InboxDue from '@/components/complaints/inbox-due';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { shortDay } from '@/lib/complaints/deadline';
import { show } from '@/routes/tenant/complaints/cases';
import type { CaseRow } from '@/types';

/** One case of the inbox: what it is, where and when it came, the deadline that counts now and its one button. Never what it says. */
export default function InboxRow({
    row,
    today,
}: {
    row: CaseRow;
    today: string;
}) {
    const tenantSlug = useTenantSlug();
    const pending = row.status !== 'closed' && !row.acknowledged;
    const action = pending ? 'Acusar recibo' : 'Abrir';

    return (
        <li className="grid items-center gap-x-6 gap-y-3 px-5 py-4 @3xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
            <div className="flex min-w-0 flex-col gap-1">
                <span className="flex flex-wrap items-center gap-2">
                    <span className="font-mono text-sm font-semibold">
                        {row.reference}
                    </span>
                    <Badge variant="outline" className="before:hidden">
                        {row.identity === 'anonymous'
                            ? 'Anónima'
                            : 'Confidencial'}
                    </Badge>
                </span>
                <span>{row.typeLabel}</span>
            </div>
            <div className="flex flex-col text-sm">
                <span>{row.center ?? 'Sin centro indicado'}</span>
                <span className="text-muted-foreground">
                    Recibida {shortDay(row.receivedOn)}
                </span>
            </div>
            <InboxDue row={row} today={today} />
            <Button
                asChild
                variant={pending ? 'default' : 'outline'}
                className="justify-self-start @3xl:justify-self-end"
            >
                <Link
                    href={show.url({ tenant: tenantSlug, complaint: row.id })}
                    aria-label={`${action}: ${row.reference}`}
                >
                    {action}
                </Link>
            </Button>
        </li>
    );
}
