import { Badge } from '@/components/ui/badge';
import { count } from '@/lib/operations';
import { connectionFacts, connectionState } from '@/lib/operations-messaging';
import type { MessagingConnection, OptOutTally } from '@/types';

/** Whether WhatsApp sends now and why not, Meta's last event, and how many people asked not to be written to. */
export default function ConnectionCard({
    connection,
    optOuts,
}: {
    connection: MessagingConnection;
    optOuts: OptOutTally;
}) {
    const state = connectionState(connection);
    const rows = [
        ...connectionFacts(connection),
        {
            label: 'Bajas en todas las empresas',
            value: count(optOuts.everyCompany),
        },
        { label: 'Bajas en una empresa', value: count(optOuts.oneCompany) },
        { label: 'Bajas en 30 días', value: count(optOuts.lastThirtyDays) },
    ];

    return (
        <section className="card p-0">
            <header className="flex items-center justify-between gap-4 border-b px-6 py-4">
                <h2 className="text-base font-semibold">Conexión con Meta</h2>
                <Badge variant={state.variant}>{state.label}</Badge>
            </header>
            <dl className="divide-y">
                {rows.map((row) => (
                    <div
                        key={row.label}
                        className="flex items-center justify-between gap-4 px-6 py-3.5"
                    >
                        <dt className="text-sm text-muted-foreground">
                            {row.label}
                        </dt>
                        <dd className="text-right font-medium tabular-nums">
                            {row.value}
                        </dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}
