import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { eventLabel } from '@/lib/audit';
import type { AuditEntryRow } from '@/types';

/** One entry of the trail: when, what, who and in which company; its context opens underneath as key and value. */
export default function AuditRow({ entry }: { entry: AuditEntryRow }) {
    const [open, setOpen] = useState(false);
    const context = Object.entries(entry.context);

    return (
        <>
            <TableRow>
                <TableCell className="text-sm whitespace-normal tabular-nums max-md:px-2">
                    {entry.at}
                </TableCell>
                <TableCell className="whitespace-normal max-md:px-2">
                    <span className="font-medium">
                        {eventLabel(entry.event)}
                    </span>
                </TableCell>
                <TableCell className="whitespace-normal max-md:px-2">
                    {entry.actor === null ? (
                        <span className="text-muted-foreground">Sistema</span>
                    ) : (
                        <span className="flex flex-col">
                            {entry.actor.name}
                            <span className="text-caption break-all text-muted-foreground">
                                {entry.actor.email}
                            </span>
                        </span>
                    )}
                    {entry.impersonator !== null && (
                        <span className="mt-1 block text-caption text-muted-foreground">
                            Impersonando: {entry.impersonator.email}
                        </span>
                    )}
                </TableCell>
                <TableCell className="hidden whitespace-normal md:table-cell">
                    {entry.company?.name ?? (
                        <span className="text-muted-foreground">
                            Plataforma
                        </span>
                    )}
                </TableCell>
                <TableCell className="w-px text-right max-md:px-2">
                    {context.length > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            aria-label={`Detalle: ${eventLabel(entry.event)}`}
                            aria-expanded={open}
                            onClick={() => setOpen(!open)}
                        >
                            Detalle
                            <ChevronDown
                                aria-hidden
                                className={open ? 'rotate-180' : ''}
                            />
                        </Button>
                    )}
                </TableCell>
            </TableRow>
            {open && (
                <TableRow>
                    <TableCell
                        colSpan={5}
                        className="bg-muted/50 whitespace-normal"
                    >
                        <dl className="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-[max-content_1fr]">
                            {entry.company !== null &&
                                entry.context['Empresa'] === undefined && (
                                    <div className="contents md:hidden">
                                        <dt className="text-muted-foreground">
                                            Empresa
                                        </dt>
                                        <dd>{entry.company.name}</dd>
                                    </div>
                                )}
                            {context.map(([key, value]) => (
                                <div key={key} className="contents">
                                    <dt className="text-muted-foreground">
                                        {key}
                                    </dt>
                                    <dd className="break-words">{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </TableCell>
                </TableRow>
            )}
        </>
    );
}
