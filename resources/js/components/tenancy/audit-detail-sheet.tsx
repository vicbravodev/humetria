import { Link } from '@inertiajs/react';
import AuditChange from '@/components/tenancy/audit-change';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { auditActor, eventLabel } from '@/lib/audit';
import { show } from '@/routes/companies';
import type { AuditEntryRow } from '@/types';

/**
 * One entry of the trail in a side panel, with the list still in view: when, who, where, what it stored and what it
 * changed. Only what the trail keeps: never an answer, nor anything that ties a person to one.
 */
export default function AuditDetailSheet({
    entry,
    open,
    onOpenChange,
}: {
    entry: AuditEntryRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const facts: [string, string][] = [
        ['Fecha', entry.at],
        ['Quién', auditActor(entry)],
        ['Empresa', entry.company?.name ?? 'Plataforma'],
        ...Object.entries(entry.context).filter(([key]) => key !== 'Empresa'),
    ];

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full gap-0 bg-popover sm:inset-y-2 sm:right-2 sm:h-auto sm:max-w-[440px] sm:rounded-xl sm:border-0 sm:shadow-float">
                <SheetHeader className="gap-0.5 border-b px-6 pt-5 pb-4">
                    <SheetTitle className="text-lg leading-[26px]">
                        {eventLabel(entry.event)}
                    </SheetTitle>
                    <SheetDescription>
                        Detalle del registro. Solo se lee: nada de aquí se edita
                        ni se borra.
                    </SheetDescription>
                </SheetHeader>
                <div className="grid flex-1 content-start gap-6 overflow-y-auto px-6 py-5">
                    <dl className="grid grid-cols-[max-content_1fr] gap-x-6 gap-y-2 text-sm">
                        {facts.map(([key, value]) => (
                            <div key={key} className="contents">
                                <dt className="text-muted-foreground">{key}</dt>
                                <dd className="break-words">{value}</dd>
                            </div>
                        ))}
                    </dl>
                    {entry.change.length > 0 && (
                        <AuditChange change={entry.change} />
                    )}
                    {entry.company !== null && (
                        <Button asChild variant="outline" className="w-fit">
                            <Link href={show(entry.company.id)}>
                                Abrir la empresa
                            </Link>
                        </Button>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
