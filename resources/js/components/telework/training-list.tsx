import { GraduationCap } from 'lucide-react';
import ConsolePagination from '@/components/console-pagination';
import EmptyState from '@/components/humetria/empty-state';
import TrainingRow from '@/components/telework/training-row';
import type { Paginated, TrainingRow as Row } from '@/types';

/** The trainings registered, the newest first. */
export default function TrainingList({
    tenantSlug,
    trainings,
}: {
    tenantSlug: string;
    trainings: Paginated<Row>;
}) {
    return (
        <section className="grid gap-3" aria-labelledby="trainings-title">
            <h2 id="trainings-title" className="text-xl font-semibold">
                Capacitaciones registradas
            </h2>
            {trainings.total === 0 ? (
                <EmptyState
                    icon={GraduationCap}
                    title="Aún no hay capacitaciones"
                >
                    Registra la primera: elige el centro, los temas del 8.1 que
                    cubrió y a quienes asistieron.
                </EmptyState>
            ) : (
                <ol aria-label="Capacitaciones" className="flex flex-col gap-3">
                    {trainings.data.map((row) => (
                        <TrainingRow
                            key={row.id}
                            tenantSlug={tenantSlug}
                            row={row}
                        />
                    ))}
                </ol>
            )}
            <ConsolePagination
                page={trainings}
                label="Páginas de capacitaciones"
                previous="Anteriores"
                next="Siguientes"
                pinned={false}
            />
        </section>
    );
}
