import { useForm } from '@inertiajs/react';
import { BellRing } from 'lucide-react';
import ArcMeter from '@/components/humetria/arc-meter';
import CountTabs from '@/components/humetria/count-tabs';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { ackQuery, progressText } from '@/lib/disclosure';
import { dissemination } from '@/routes/tenant/disclosure';
import { store } from '@/routes/tenant/disclosure/dissemination/reminders';
import type {
    PolicyAckFilters,
    PolicyAckProgress,
    PolicyAckStatus,
} from '@/types';

const VIEWS: { status: PolicyAckStatus; label: string }[] = [
    { status: 'all', label: 'Todas' },
    { status: 'pending', label: 'Pendientes' },
    { status: 'confirmed', label: 'Confirmaron' },
];

/**
 * How far the confirmations of a version are (pantalla Eval-Activa: the arch and its figure) with the views that split the
 * people by state and the reminder to those pending.
 */
export default function DisseminationSummary({
    documentId,
    version,
    progress,
    counts,
    filters,
}: {
    documentId: string;
    version: string | null;
    progress: PolicyAckProgress;
    counts: Record<PolicyAckStatus, number>;
    filters: PolicyAckFilters;
}) {
    const tenantSlug = useTenantSlug();
    const remind = useForm({});

    return (
        <section
            aria-label="Avance de las confirmaciones"
            className="flex flex-col gap-5 card p-5 md:p-6"
        >
            <div className="flex flex-wrap items-center gap-6">
                <ArcMeter
                    value={progress.confirmed}
                    max={progress.active}
                    label={progressText(progress)}
                    size="md"
                >
                    <span className="text-2xl font-semibold tabular-nums">
                        {progress.percent}&nbsp;%
                    </span>
                </ArcMeter>
                <div className="flex min-w-0 flex-1 flex-col gap-1">
                    <p className="text-lg font-semibold">
                        {progressText(progress)}
                    </p>
                    <p className="text-meta text-muted-foreground">
                        Cuenta a quienes hoy trabajan en los centros de esta
                        versión: si alguien se da de baja, deja de contar. La
                        confirmación es una constancia más; la norma no la
                        exige.
                    </p>
                </div>
                <Button
                    variant="outline"
                    disabled={remind.processing || progress.pending === 0}
                    onClick={() =>
                        remind.post(
                            store.url({
                                tenant: tenantSlug,
                                document: documentId,
                            }),
                            { preserveScroll: true },
                        )
                    }
                >
                    {remind.processing ? <Spinner /> : <BellRing aria-hidden />}
                    Recordar a los pendientes
                </Button>
            </div>
            <CountTabs
                label="Estado de las confirmaciones"
                tabs={VIEWS.map((view) => ({
                    label: view.label,
                    count: counts[view.status],
                    active: filters.status === view.status,
                    href: dissemination.url(tenantSlug, {
                        query: ackQuery({
                            status: view.status,
                            q: filters.q,
                            version,
                        }),
                    }),
                }))}
            />
        </section>
    );
}
