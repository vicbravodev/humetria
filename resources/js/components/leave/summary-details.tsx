import { CalendarCheck } from 'lucide-react';
import CountTabs from '@/components/humetria/count-tabs';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import OutList from '@/components/leave/out-list';
import WindowsList from '@/components/leave/windows-list';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/vacations';
import type { LeaveSummaryRow, LeaveSummaryView, Paginated } from '@/types';

const TABS: { view: LeaveSummaryView; label: string; empty: string }[] = [
    { view: 'out', label: 'Fuera hoy', empty: 'Nadie está fuera hoy.' },
    {
        view: 'windows',
        label: 'Ventanas por cerrar',
        empty: 'Ningún periodo con días está por cerrar su plazo.',
    },
    {
        view: 'prescribable',
        label: 'Podrían prescribir',
        empty: 'Ningún periodo con días pasó de sus 18 meses.',
    },
];

/** The detail under the summary: a tab per list, with how many each holds, and the one open paged by the server. */
export default function SummaryDetails({
    view,
    counts,
    people,
}: {
    view: LeaveSummaryView;
    counts: Record<LeaveSummaryView, number>;
    people: Paginated<LeaveSummaryRow>;
}) {
    const tenantSlug = useTenantSlug();
    const current = TABS.find((tab) => tab.view === view);

    return (
        <section aria-labelledby="leave-detail" className="flex flex-col gap-4">
            <h2 id="leave-detail" className="text-xl leading-7 font-semibold">
                Detalle
            </h2>
            <CountTabs
                label="Listas del resumen"
                tabs={TABS.map((tab) => ({
                    label: tab.label,
                    count: counts[tab.view],
                    active: tab.view === view,
                    href: index.url(tenantSlug, { query: { view: tab.view } }),
                }))}
            />
            {people.data.length === 0 ? (
                <EmptyState icon={CalendarCheck} title={current?.empty ?? ''}>
                    Cuando haya alguien, aparecerá aquí.
                </EmptyState>
            ) : view === 'out' ? (
                <OutList rows={people.data} />
            ) : (
                <WindowsList
                    rows={people.data}
                    prescribable={view === 'prescribable'}
                />
            )}
            <Pagination page={people} label="Páginas del detalle" />
        </section>
    );
}
