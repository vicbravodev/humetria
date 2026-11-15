import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { splitByAction } from '@/lib/count-tabs';
import { cn } from '@/lib/utils';

export type CountTab = {
    label: string;
    /** How many the view holds; a view without a count (a form, a document) shows none. */
    count?: number | undefined;
    href: NonNullable<InertiaLinkProps['href']>;
    active: boolean;
    /** With `byAction`: this view is what waits for someone, so it goes first. */
    needsAction?: boolean | undefined;
};

/**
 * The views of one list with how many each holds (Pendientes 42 · Contestaron 18 · Todas 60). Each is a link, so the
 * view lives in the URL and the server pages it.
 */
export default function CountTabs({
    label,
    tabs,
    byAction = false,
}: {
    /** What the views split, for screen readers: "Estado de las personas". */
    label: string;
    tabs: CountTab[];
    /** What asks for action goes first and a view with none is not drawn as a tab but under «Otros filtros». */
    byAction?: boolean;
}) {
    const { shown, hidden } = byAction
        ? splitByAction(tabs)
        : { shown: tabs, hidden: [] };

    return (
        <nav
            aria-label={label}
            className="inline-flex max-w-full gap-0.5 overflow-x-auto rounded-[10px] border bg-muted p-[3px]"
        >
            {shown.map((tab) => (
                <Link
                    key={tab.label}
                    href={tab.href}
                    preserveScroll
                    aria-current={tab.active ? 'page' : undefined}
                    className={cn(
                        'relative inline-flex h-[38px] shrink-0 items-center gap-2 rounded-lg px-3.5 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors duration-150 ease-out before:absolute before:inset-x-0 before:-inset-y-[3px] hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                        tab.active && 'bg-card text-foreground shadow-raised',
                    )}
                >
                    {tab.label}
                    {tab.count !== undefined && (
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
                    )}
                </Link>
            ))}
            {hidden.length > 0 && (
                <DropdownMenu modal={false}>
                    <DropdownMenuTrigger className="relative inline-flex h-[38px] shrink-0 items-center gap-1 rounded-lg px-3.5 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors duration-150 ease-out before:absolute before:inset-x-0 before:-inset-y-[3px] hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                        Otros filtros
                        <ChevronDown aria-hidden className="size-4" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {hidden.map((tab) => (
                            <DropdownMenuItem key={tab.label} asChild>
                                <Link href={tab.href} preserveScroll>
                                    {tab.label}
                                </Link>
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
        </nav>
    );
}
