import { ChevronDown } from 'lucide-react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { countOf } from '@/lib/plural';
import type { EmployeeImportChange } from '@/types';

/** Folds open on their own up to this many people: a short review shows what changes without a click. */
const OPEN_UP_TO = 5;

/** What the file changes in each person who already exists, before it is confirmed; one fold per person. */
export default function ImportChanges({
    changes,
}: {
    changes: EmployeeImportChange[];
}) {
    return (
        <section aria-labelledby="import-changes" className="grid gap-3">
            <div>
                <h2
                    id="import-changes"
                    className="text-lg leading-[26px] font-semibold"
                >
                    Qué cambia en{' '}
                    {countOf(changes.length, 'persona', 'personas')}
                </h2>
                <p className="text-sm text-muted-foreground">
                    Revisa antes de confirmar: lo que el archivo no trae no se
                    toca.
                </p>
            </div>
            <ul className="divide-y divide-border card">
                {changes.map((person) => (
                    <li key={person.row}>
                        <details
                            className="group px-5 py-3"
                            open={changes.length <= OPEN_UP_TO}
                        >
                            <summary className="flex cursor-pointer list-none items-center justify-between gap-3 font-medium">
                                <span>{person.name}</span>
                                <span className="flex items-center gap-2 text-sm font-normal text-muted-foreground">
                                    {countOf(
                                        person.changes.length,
                                        'cambio',
                                        'cambios',
                                    )}
                                    <ChevronDown
                                        aria-hidden="true"
                                        className="size-4 transition-transform group-open:rotate-180"
                                    />
                                </span>
                            </summary>
                            <Table className="mt-2 text-sm [&_td]:whitespace-normal">
                                <TableHeader className="bg-muted/60">
                                    <TableRow>
                                        <TableHead>Campo</TableHead>
                                        <TableHead>Antes</TableHead>
                                        <TableHead>Después</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {person.changes.map((change) => (
                                        <TableRow key={change.label}>
                                            <TableCell className="font-medium">
                                                {change.label}
                                            </TableCell>
                                            <TableCell>
                                                {change.before}
                                            </TableCell>
                                            <TableCell>
                                                {change.after === '—' ? (
                                                    <span
                                                        data-erased
                                                        className="inline-flex rounded-md bg-warning px-2 py-0.5 font-medium text-warning-foreground"
                                                    >
                                                        Se borra
                                                    </span>
                                                ) : (
                                                    change.after
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </details>
                    </li>
                ))}
            </ul>
        </section>
    );
}
