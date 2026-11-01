import { ListChecks } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { TrainingChoice } from '@/types';

/** The 8.1 topics the chosen center has not covered in the last twelve months; without a center, how to see them. */
export default function TrainingCoverage({
    uncovered,
}: {
    uncovered: TrainingChoice[] | null;
}) {
    return (
        <section
            className="flex flex-col gap-3 rounded-xl border bg-card p-5"
            aria-labelledby="coverage-title"
        >
            <div className="flex items-center gap-3">
                <span className="flex size-10 items-center justify-center rounded-lg bg-accent text-primary">
                    <ListChecks aria-hidden className="size-5" />
                </span>
                <div>
                    <h2 id="coverage-title" className="text-base font-semibold">
                        Temas del 8.1 sin cubrir
                    </h2>
                    <p className="text-[15px] text-muted-foreground">
                        {uncovered === null
                            ? 'Elige un centro de trabajo para ver qué temas no se han cubierto en los últimos 12 meses.'
                            : uncovered.length === 0
                              ? 'Este centro cubrió todos los temas en los últimos 12 meses.'
                              : 'Ninguna capacitación de este centro los cubrió en los últimos 12 meses.'}
                    </p>
                </div>
            </div>
            {uncovered !== null && uncovered.length > 0 && (
                <ul
                    aria-label="Temas sin cubrir"
                    className="flex flex-wrap gap-2"
                >
                    {uncovered.map((topic) => (
                        <li key={topic.value}>
                            <Badge variant="warning">{topic.label}</Badge>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
