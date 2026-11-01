import CountTabs from '@/components/humetria/count-tabs';
import ProgressBar from '@/components/humetria/progress-bar';
import { ackQuery, progressText } from '@/lib/telework/acknowledgements';
import { index } from '@/routes/tenant/telework/documents/acknowledgements';
import type { AckFilters, AckProgress } from '@/types';

const VIEWS = [
    { status: 'all', label: 'Todas' },
    { status: 'pending', label: 'Pendientes' },
    { status: 'acknowledged', label: 'Confirmaron' },
] as const;

/** How far the confirmations are (written next to the bar) and the views that split the people by state. */
export default function AckSummary({
    tenantSlug,
    documentId,
    progress,
    counts,
    filters,
}: {
    tenantSlug: string;
    documentId: string;
    progress: AckProgress;
    counts: Record<AckFilters['status'], number>;
    filters: AckFilters;
}) {
    return (
        <section
            aria-label="Avance de las confirmaciones"
            className="flex flex-col gap-4 rounded-xl border bg-card px-[22px] py-5"
        >
            <div className="flex flex-col gap-2">
                <p className="font-medium">{progressText(progress)}</p>
                <ProgressBar
                    value={progress.acknowledged}
                    max={progress.expected}
                    label="Personas que confirmaron"
                />
                <p className="text-[13px] leading-[18px] text-muted-foreground">
                    Cuenta a quienes hoy deben confirmar: si alguien se da de
                    baja, deja de contar.
                </p>
            </div>
            <CountTabs
                label="Estado de las confirmaciones"
                tabs={VIEWS.map((view) => ({
                    label: view.label,
                    count: counts[view.status],
                    active: filters.status === view.status,
                    href: index.url(
                        { tenant: tenantSlug, document: documentId },
                        {
                            query: ackQuery({
                                status: view.status,
                                q: filters.q,
                            }),
                        },
                    ),
                }))}
            />
        </section>
    );
}
