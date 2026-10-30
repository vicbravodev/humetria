import type { Kpi } from '@/lib/dashboard';

/** A figure of the panel's evaluation card, with what it counts under it. */
export default function DashboardKpi({ kpi }: { kpi: Kpi }) {
    return (
        <p className="flex flex-col gap-0.5">
            <span className="text-[28px] leading-[34px] font-semibold tracking-[-0.01em] tabular-nums">
                {kpi.value}
            </span>
            <span className="text-sm text-muted-foreground">{kpi.caption}</span>
        </p>
    );
}
