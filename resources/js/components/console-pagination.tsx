import { ChevronLeft, ChevronRight } from 'lucide-react';
import PageButton from '@/components/page-button';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

/**
 * The pages of a list: which rows are on screen and the way to the next ones. Pinned to the bottom of the screen
 * while the list scrolls, so turning the page never means scrolling to the end first.
 */
export default function ConsolePagination({
    page,
    label,
    previous = 'Ver más recientes',
    next = 'Ver más antiguas',
    preserveState = false,
    pinned = true,
}: {
    page: Paginated<unknown>;
    label: string;
    /** The buttons' text: by default the consoles' newest-first order. */
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

    return (
        <nav
            aria-label={label}
            className={cn(
                'flex flex-wrap items-center justify-between gap-x-4 gap-y-2',
                pinned &&
                    'sticky bottom-0 z-10 -mx-4 -mb-6 border-t bg-background px-4 py-3 md:-mx-10 md:-mb-8 md:px-10 print:static',
            )}
        >
            <p className="text-sm text-muted-foreground tabular-nums">
                {page.from ?? 0}–{page.to ?? 0} de {page.total} · Página{' '}
                {page.current_page} de {page.last_page}
            </p>
            <div className="flex flex-wrap gap-2">
                <PageButton
                    href={page.prev_page_url}
                    label={previous}
                    icon={ChevronLeft}
                    preserveState={preserveState}
                />
                <PageButton
                    href={page.next_page_url}
                    label={next}
                    icon={ChevronRight}
                    iconAfter
                    preserveState={preserveState}
                />
            </div>
        </nav>
    );
}
