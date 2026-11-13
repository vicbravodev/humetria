import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

/**
 * The pages of a list: which rows are on screen and the way to the next ones. Pinned to the bottom of the screen
 * while the list scrolls on a desk (on a phone the bar of the menu floats there, so it follows the list), so turning the page never means scrolling to the end first.
 */
export default function Pagination({
    page,
    label,
    previous = 'Anteriores',
    next = 'Siguientes',
    preserveState = false,
    pinned = true,
}: {
    page: Paginated<unknown>;
    label: string;
    /** The buttons' text; a list from the newest says "Ver más recientes" and "Ver más antiguas". */
    previous?: string;
    next?: string;
    /** Keep what the page holds that is not in its address, like a selection, when turning pages. */
    preserveState?: boolean;
    /** Stay in view at the bottom while the list scrolls; off inside a form or a smaller block of a page. */
    pinned?: boolean;
}) {
    if (page.last_page <= 1) {
        return null;
    }

    // One step: a link to that page, or the same button disabled when there is no such page.
    const step = (href: string | null, content: ReactNode) =>
        href === null ? (
            <Button variant="outline" disabled>
                {content}
            </Button>
        ) : (
            <Button variant="outline" asChild>
                <Link href={href} preserveState={preserveState}>
                    {content}
                </Link>
            </Button>
        );

    return (
        <nav
            aria-label={label}
            className={cn(
                'flex flex-wrap items-center justify-between gap-x-4 gap-y-2',
                pinned &&
                    '-mx-4 border-t bg-background px-4 py-3 md:sticky md:bottom-0 md:z-10 md:-mx-10 md:-mb-8 md:px-10 print:static',
            )}
        >
            <p className="text-sm text-muted-foreground tabular-nums">
                {page.from ?? 0}–{page.to ?? 0} de {page.total} · Página{' '}
                {page.current_page} de {page.last_page}
            </p>
            <div className="flex flex-wrap gap-2">
                {step(
                    page.prev_page_url,
                    <>
                        <ChevronLeft aria-hidden="true" />
                        {previous}
                    </>,
                )}
                {step(
                    page.next_page_url,
                    <>
                        {next}
                        <ChevronRight aria-hidden="true" />
                    </>,
                )}
            </div>
        </nav>
    );
}
