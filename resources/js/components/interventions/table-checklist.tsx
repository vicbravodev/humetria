import {
    Circle,
    CircleCheck,
    CircleDashed,
    CircleDot,
    Minus,
} from 'lucide-react';
import { requirementText } from '@/lib/interventions';
import { cn } from '@/lib/utils';
import type { Requirement } from '@/types';

const ICONS = {
    planned: Circle,
    in_progress: CircleDot,
    done: CircleCheck,
    missing: CircleDashed,
    optional: Minus,
};

/**
 * «Lo que pide la Tabla» (II.4 and III.4; criterio C-34): what the norm asks the Programa to include at the highest level of
 * the evaluation, and where each line stands: planned, running or done (its most advanced action), or missing. It only says
 * what is recorded, never whether it is enough.
 */
export default function TableChecklist({
    requirements,
}: {
    requirements: Requirement[];
}) {
    if (requirements.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="table-title"
            className="flex flex-col gap-3.5 card p-6"
        >
            <h2 id="table-title" className="text-lg leading-7 font-semibold">
                Lo que pide la Tabla
            </h2>
            <p className="text-sm text-muted-foreground">
                Según el nivel más alto del resultado. Cada línea dice cómo va
                su acción más avanzada: no juzga si basta.
            </p>
            <ul className="grid gap-2.5">
                {requirements.map((line) => {
                    const Icon = ICONS[line.status];

                    return (
                        <li key={line.key} className="flex items-start gap-3">
                            <Icon
                                aria-hidden
                                className={cn(
                                    'mt-0.5 size-5 shrink-0',
                                    line.status === 'done'
                                        ? 'text-primary'
                                        : 'text-muted-foreground',
                                )}
                            />
                            <span className="grid">
                                {line.text}
                                <span className="text-sm text-muted-foreground">
                                    {requirementText(line)}
                                </span>
                            </span>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
