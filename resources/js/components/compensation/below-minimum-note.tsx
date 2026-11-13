import { TriangleAlert } from 'lucide-react';

/** Why a salary is under the general minimum wage, in its own row: it stays after the toast of saving is gone. */
export default function BelowMinimumNote({ reason }: { reason: string }) {
    return (
        <p className="mt-1 flex items-start gap-1 rounded-md bg-warning px-2 py-1 text-caption font-normal text-warning-foreground">
            <TriangleAlert
                aria-hidden="true"
                className="mt-0.5 size-3.5 shrink-0"
            />
            <span>
                <span className="font-medium">Bajo el salario mínimo.</span>{' '}
                {reason}
            </span>
        </p>
    );
}
