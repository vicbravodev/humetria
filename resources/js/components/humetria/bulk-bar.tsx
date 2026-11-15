import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { countOf } from '@/lib/plural';

/**
 * What can be done with the rows chosen in a table ("3 seleccionadas · Enviar acuse · Quitar selección"): it sticks to
 * the bottom of the table's container only while something is chosen, never as permanent buttons.
 */
export default function BulkBar({
    count,
    one = 'seleccionada',
    many = 'seleccionadas',
    onClear,
    children,
}: {
    count: number;
    /** What the count says for exactly one and for more: "seleccionada" / "seleccionadas". */
    one?: string;
    many?: string;
    onClear: () => void;
    /** The actions, the primary one first. */
    children: ReactNode;
}) {
    if (count <= 0) {
        return null;
    }

    return (
        <div
            role="region"
            aria-label="Acciones de la selección"
            data-slot="bulk-bar"
            className="sticky bottom-0 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-b-xl border-t bg-card px-4 py-3"
        >
            <p aria-live="polite" className="font-medium tabular-nums">
                {countOf(count, one, many)}
            </p>
            <div className="flex flex-wrap items-center gap-2">{children}</div>
            <Button
                variant="ghost"
                onClick={onClear}
                className="ml-auto text-muted-foreground"
            >
                Quitar selección
            </Button>
        </div>
    );
}
