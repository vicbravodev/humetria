import { Link } from '@inertiajs/react';
import OverviewBreakdown from '@/components/tenancy/overview-breakdown';
import { DEMO_VIEWS, demoQuery } from '@/lib/demo-requests';
import { demosLine } from '@/lib/overview';
import { index as demoRequests } from '@/routes/demo-requests';
import type { PlatformOverviewData } from '@/types';

/** «Solicitudes de demo» (tablero Hoy, «Embudo de demos»): how many wait, and each stage a way into its list. */
export default function DemoFunnelCard({
    demos,
}: Pick<PlatformOverviewData, 'demos'>) {
    return (
        <section className="card">
            <div className="flex items-center justify-between gap-3 border-b px-6 py-4">
                <h2 className="text-lg font-semibold">Solicitudes de demo</h2>
                <Link
                    href={demoRequests({ query: { view: 'board' } })}
                    className="inline-flex min-h-11 items-center text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    Abrir
                </Link>
            </div>
            <div className="grid gap-2 px-4 py-4">
                <p className="px-2 text-sm text-muted-foreground">
                    {demosLine(demos.new)}
                </p>
                <OverviewBreakdown
                    label="Solicitudes por estado"
                    rows={DEMO_VIEWS.filter((view) => view.value !== 'all').map(
                        (view) => ({
                            label: view.label,
                            count: demos[view.value],
                            href: demoRequests.url({
                                query: demoQuery(view.value),
                            }),
                        }),
                    )}
                />
            </div>
        </section>
    );
}
