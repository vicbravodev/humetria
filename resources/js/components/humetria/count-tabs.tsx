import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export type CountTab = {
    label: string;
    count: number;
    href: NonNullable<InertiaLinkProps['href']>;
    active: boolean;
};

/**
 * The views of one list with how many each holds (Pendientes 42 · Contestaron 18 · Todas 60). Each is a link, so the
 * view lives in the URL and the server pages it.
 */
export default function CountTabs({
    label,
    tabs,
}: {
    /** What the views split, for screen readers: "Estado de las personas". */
    label: string;
    tabs: CountTab[];
}) {
    return (
        <nav
            aria-label={label}
            className="inline-flex max-w-full gap-0.5 overflow-x-auto rounded-[10px] border bg-muted p-[3px]"
        >
            {tabs.map((tab) => (
                <Link
                    key={tab.label}
                    href={tab.href}
                    preserveScroll
                    aria-current={tab.active ? 'page' : undefined}
                    className={cn(
                        'inline-flex h-[38px] shrink-0 items-center gap-2 rounded-lg px-3.5 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors duration-150 ease-out before:absolute before:inset-x-0 before:-inset-y-[3px] hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                        tab.active && 'bg-card text-foreground shadow-raised',
                    )}
                >
                    {tab.label}
                    <span
                        className={cn(
                            'rounded-full px-[7px] text-xs leading-5 tabular-nums',
                            tab.active
                                ? 'bg-accent text-primary'
                                : 'bg-track text-muted-foreground',
                        )}
                    >
                        {tab.count}
                    </span>
                </Link>
            ))}
        </nav>
    );
}
