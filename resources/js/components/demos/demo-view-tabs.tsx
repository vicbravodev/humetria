import { Link } from '@inertiajs/react';
import { demoListQuery } from '@/lib/demo-requests';
import { cn } from '@/lib/utils';
import { index } from '@/routes/demo-requests';
import type { DemoFilters, DemoLayout } from '@/types';

const VIEWS: { value: DemoLayout; label: string }[] = [
    { value: 'board', label: 'Embudo' },
    { value: 'list', label: 'Lista' },
];

/** The funnel or the list of every request, beside the title (tablero Demos); each is its own address, filters kept. */
export default function DemoViewTabs({ filters }: { filters: DemoFilters }) {
    return (
        <nav aria-label="Vista de las solicitudes" className="flex gap-1">
            {VIEWS.map((view) => (
                <Link
                    key={view.value}
                    href={index.url({
                        query: demoListQuery({
                            ...filters,
                            status: 'all',
                            view: view.value,
                        }),
                    })}
                    preserveScroll
                    aria-current={
                        filters.view === view.value ? 'page' : undefined
                    }
                    className={cn(
                        'inline-flex h-11 items-center border-b-2 border-transparent px-3.5 font-medium whitespace-nowrap text-muted-foreground transition-colors duration-150 ease-out hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                        filters.view === view.value &&
                            'border-primary text-foreground',
                    )}
                >
                    {view.label}
                </Link>
            ))}
        </nav>
    );
}
