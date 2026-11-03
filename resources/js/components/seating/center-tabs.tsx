import CountTabs from '@/components/humetria/count-tabs';
import { useSeatingHref } from '@/hooks/use-seating-href';
import type { SeatingCenterTab } from '@/types';

/** The parts of a center with how many records each holds (Puestos 3 · Personas 12 · Inventario 5); only those the person may open. */
export default function CenterTabs({
    workCenterId,
    tabs,
}: {
    workCenterId: string;
    tabs: SeatingCenterTab[];
}) {
    const hrefOf = useSeatingHref(workCenterId);

    return (
        <CountTabs
            label="Secciones del centro"
            tabs={tabs.map((tab) => ({
                label: tab.label,
                count: tab.count ?? undefined,
                href: hrefOf(tab.key),
                active: tab.key === 'positions',
            }))}
        />
    );
}
