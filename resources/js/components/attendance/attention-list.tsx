import { CircleCheck } from 'lucide-react';
import AttentionRow from '@/components/attendance/attention-row';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { attentionItems } from '@/lib/attendance/attention';
import type { AttendanceOverviewProps } from '@/types';

/**
 * «Necesita tu atención» (board A1): what waits for RH, ordered by what blocks most, before any detail. Each row says what
 * it is and has the button that settles it; with nothing pending it says so.
 */
export default function AttentionList({
    week,
    center,
    counts,
    pendingCorrections,
    centersWithoutZones,
    setup,
}: Pick<
    AttendanceOverviewProps,
    | 'week'
    | 'center'
    | 'counts'
    | 'pendingCorrections'
    | 'centersWithoutZones'
    | 'setup'
>) {
    const items = attentionItems(useTenantSlug(), {
        week: week.start,
        over: week.over,
        center: center?.id ?? '',
        counts,
        pendingCorrections,
        centersWithoutZones,
        findings: setup.findings,
    });

    return (
        <section
            aria-labelledby="atencion"
            className="flex min-w-0 flex-col gap-3"
        >
            <div>
                <h2 id="atencion" className="text-xl leading-7 font-semibold">
                    Necesita tu atención
                </h2>
                <p className="text-sm text-muted-foreground">
                    Ordenado por lo que más bloquea: primero lo que espera una
                    decisión tuya.
                </p>
            </div>
            {items.length === 0 ? (
                <p className="flex items-center gap-3 card px-5 py-4 text-muted-foreground">
                    <CircleCheck aria-hidden className="size-5 text-primary" />
                    Nada requiere tu atención por ahora.
                </p>
            ) : (
                <ul className="divide-y overflow-hidden card">
                    {items.map((item, index) => (
                        <AttentionRow
                            key={item.key}
                            item={item}
                            first={index === 0}
                        />
                    ))}
                </ul>
            )}
        </section>
    );
}
