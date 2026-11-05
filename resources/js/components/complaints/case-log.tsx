import { ChevronRight } from 'lucide-react';
import Pagination from '@/components/humetria/pagination';
import { shortDay } from '@/lib/complaints/deadline';
import type { CaseEntryRow, Paginated } from '@/types';

/**
 * «Bitácora del caso» (board V4-Quejas-Caso), folded: what happened to the case and who has it. Each reading of the case is
 * in the company's audit log too.
 */
export default function CaseLog({
    log,
    access,
    received,
}: {
    log: Paginated<CaseEntryRow>;
    access: string[];
    received: string;
}) {
    return (
        <details className="group card p-6 [&_summary::-webkit-details-marker]:hidden">
            <summary className="-my-2.5 flex min-h-11 cursor-pointer items-center gap-2 rounded-sm text-lg font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                <ChevronRight
                    aria-hidden
                    className="size-4 transition-transform group-open:rotate-90"
                />
                Bitácora del caso
            </summary>
            <div className="mt-4 flex flex-col gap-4 text-sm">
                <p>
                    <span className="font-medium">Lo tienen: </span>
                    {access.join(', ')}
                </p>
                <ol className="flex flex-col gap-2">
                    {log.data.map((entry) => (
                        <li key={entry.id}>
                            <span className="text-muted-foreground">
                                {entry.when === null
                                    ? ''
                                    : shortDay(entry.when)}
                                {entry.time === null ? '' : ` ${entry.time}`}
                                {' · '}
                            </span>
                            {entry.author ?? 'El sistema'} {entry.text}
                        </li>
                    ))}
                    {log.current_page >= log.last_page && (
                        <li>
                            <span className="text-muted-foreground">
                                {shortDay(received)} ·{' '}
                            </span>
                            Se recibió en el canal y se asignó.
                        </li>
                    )}
                </ol>
                <Pagination
                    page={log}
                    label="Páginas de la bitácora"
                    previous="Más recientes"
                    next="Anteriores"
                    pinned={false}
                />
                <p className="text-muted-foreground">
                    Cada vez que alguien abre el caso queda registrado, con
                    quién y cuándo.
                </p>
            </div>
        </details>
    );
}
