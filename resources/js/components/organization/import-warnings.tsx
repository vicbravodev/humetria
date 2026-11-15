import { TriangleAlert } from 'lucide-react';
import { countOf } from '@/lib/plural';

/** Rows that import but leave something incomplete: the file is valid, and HR still sees what each one will miss. */
export default function ImportWarnings({
    warnings,
}: {
    warnings: { row: number; messages: string[] }[];
}) {
    return (
        <section aria-labelledby="import-warnings" className="grid gap-3">
            <div>
                <h2
                    id="import-warnings"
                    className="text-lg leading-[26px] font-semibold"
                >
                    {countOf(warnings.length, 'fila', 'filas')} con un aviso
                </h2>
                <p className="text-sm text-muted-foreground">
                    Se importan igual; puedes completarlas después.
                </p>
            </div>
            <ul className="divide-y divide-border card">
                {warnings.map(({ row, messages }) => (
                    <li key={row} className="flex gap-3 px-5 py-3 text-sm">
                        <TriangleAlert
                            aria-hidden
                            className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        <span>
                            <span className="font-medium">Fila {row}:</span>{' '}
                            {messages.join(' ')}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
