import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { barPercent, retroText } from '@/lib/compensation/start';
import { report } from '@/routes/tenant/compensation/scenarios';
import type { ScenarioRow } from '@/types';

/** A scenario on the start (board V4-Comp-Inicio): what it proposes, its bar against the dearest, its first year and each year after. */
export default function ScenarioStartRow({
    scenario,
    dearest,
}: {
    scenario: ScenarioRow;
    dearest: number;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <li className="@container">
            <Link
                href={report({ tenant: tenantSlug, scenario: scenario.id })}
                className="grid grid-cols-2 gap-x-4 gap-y-3 px-6 py-5 transition-colors hover:bg-muted/50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring @[640px]:grid-cols-[minmax(0,1fr)_auto_auto_auto] @[640px]:items-center @[640px]:gap-8"
            >
                <span className="col-span-2 grid min-w-0 gap-1.5 @[640px]:col-span-1">
                    <span className="font-semibold">{scenario.name}</span>
                    <span className="text-sm text-muted-foreground">
                        {scenario.raise_text} ·{' '}
                        {retroText(scenario.retroactive_months)}
                    </span>
                    <span
                        aria-hidden
                        className="mt-1 block h-1.5 max-w-[520px] overflow-hidden rounded-full bg-muted"
                    >
                        <span
                            className="block h-full rounded-full bg-primary"
                            style={{
                                width: `${barPercent(scenario.first_year_cents, dearest)}%`,
                            }}
                        />
                    </span>
                </span>
                <span className="grid @[640px]:justify-items-end">
                    <span className="text-sm text-muted-foreground">
                        Primer año
                    </span>
                    <span className="text-xl font-semibold tabular-nums">
                        {scenario.first_year_text}
                    </span>
                    <span className="text-sm text-muted-foreground">
                        +{scenario.over_today_text} % sobre hoy
                    </span>
                </span>
                <span className="grid @[640px]:justify-items-end">
                    <span className="text-sm text-muted-foreground">
                        Cada año después
                    </span>
                    <span className="font-semibold tabular-nums">
                        {scenario.recurring_text}
                    </span>
                </span>
                <ChevronRight
                    aria-hidden
                    className="hidden size-5 text-muted-foreground @[640px]:block"
                />
            </Link>
        </li>
    );
}
