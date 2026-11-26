import { Link } from '@inertiajs/react';
import AuditChange from '@/components/tenancy/audit-change';
import { Button } from '@/components/ui/button';
import { auditActor, eventLabel } from '@/lib/audit';
import { show } from '@/routes/companies';
import type { AuditEntryRow } from '@/types';

/**
 * What the detail says of an entry (tablero Bitácora): when, who, where, what and from where, what it stored and what it
 * changed. Only what the trail keeps: never an answer, nor anything that ties a person to one.
 */
export default function AuditDetailBody({ entry }: { entry: AuditEntryRow }) {
    const facts: [string, string][] = [
        ['Fecha', entry.at],
        ['Quién', auditActor(entry)],
        ['Empresa', entry.company?.name ?? 'Plataforma'],
        ['Acción', eventLabel(entry.event)],
        ['Origen', entry.origin],
        ...Object.entries(entry.context).filter(([key]) => key !== 'Empresa'),
    ];

    return (
        <>
            <dl className="text-sm">
                {facts.map(([key, value]) => (
                    <div
                        key={key}
                        className="flex justify-between gap-4 border-t px-6 py-3 first:border-t-0"
                    >
                        <dt className="shrink-0 text-muted-foreground">
                            {key}
                        </dt>
                        <dd className="text-right font-medium [overflow-wrap:anywhere]">
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>
            {entry.change.length > 0 && (
                <div className="border-t px-6 py-4">
                    <AuditChange change={entry.change} />
                </div>
            )}
            {entry.company !== null && (
                <div className="px-6 pb-5">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="-ml-3 text-primary"
                    >
                        <Link href={show(entry.company.id)}>
                            Abrir la empresa
                        </Link>
                    </Button>
                </div>
            )}
        </>
    );
}
