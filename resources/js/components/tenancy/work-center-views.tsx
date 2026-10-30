import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { index } from '@/routes/tenant/work-centers';
import type { WorkCentersView } from '@/types';

/**
 * Active and archived work centers, as the design system's Tabs look: a card pill on a muted track.
 * Each view is its own address, so they are links, not tabs.
 */
export default function WorkCenterViews({
    tenantSlug,
    view,
    counts,
}: {
    tenantSlug: string;
    view: WorkCentersView;
    counts: Record<WorkCentersView, number>;
}) {
    const views: { value: WorkCentersView; label: string; href: string }[] = [
        { value: 'active', label: 'Activos', href: index.url(tenantSlug) },
        {
            value: 'archived',
            label: 'Archivados',
            href: index.url(tenantSlug, { query: { archived: 1 } }),
        },
    ];

    return (
        <nav
            aria-label="Vistas de centros de trabajo"
            className="flex w-fit gap-1 rounded-lg bg-muted p-1"
        >
            {views.map(({ value, label, href }) => (
                <Link
                    key={value}
                    href={href}
                    aria-current={view === value ? 'page' : undefined}
                    className={cn(
                        'rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                        view === value && 'bg-card text-foreground',
                    )}
                >
                    {label}{' '}
                    <span className="tabular-nums">{counts[value]}</span>
                </Link>
            ))}
        </nav>
    );
}
