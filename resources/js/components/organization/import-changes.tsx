import { ChevronDown } from 'lucide-react';
import { changeLine } from '@/lib/employees';
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
                            <ul className="mt-2 grid gap-1 text-sm">
                                {person.changes.map((change) => (
                                    <li key={change.label}>
                                        {changeLine(change)}
                                    </li>
                                ))}
                            </ul>
                        </details>
                    </li>
                ))}
            </ul>
        </section>
    );
}
