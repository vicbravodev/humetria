import { ChevronDown } from 'lucide-react';
import type { RoleMatrix as Matrix } from '@/types';

/**
 * «Qué puede hacer cada quien» on a phone: one folded row per role instead of a table of dozens of rows and many columns;
 * opening it lists what that role can do.
 */
export default function RoleMatrixByRole({ matrix }: { matrix: Matrix }) {
    return (
        <ul className="divide-y">
            {matrix.columns.map((column, index) => {
                const can = matrix.rows
                    .filter((row) => row.cells[index] === 'Sí')
                    .map((row) => row.label);

                return (
                    <li key={column}>
                        <details className="group">
                            <summary className="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-3.5 font-medium focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden">
                                <span className="flex flex-col">
                                    {column}
                                    <span className="text-sm font-normal text-muted-foreground">
                                        {can.length} de {matrix.rows.length}{' '}
                                        permisos
                                    </span>
                                </span>
                                <ChevronDown
                                    aria-hidden
                                    className="size-5 shrink-0 text-muted-foreground transition-transform group-open:rotate-180"
                                />
                            </summary>
                            <ul className="flex flex-col gap-1.5 px-5 pb-4 text-sm">
                                {can.map((label) => (
                                    <li key={label}>{label}</li>
                                ))}
                            </ul>
                        </details>
                    </li>
                );
            })}
        </ul>
    );
}
