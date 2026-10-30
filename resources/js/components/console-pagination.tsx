import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

export default function ConsolePagination({
    page,
    label,
    previous = 'Ver más recientes',
    next = 'Ver más antiguas',
    preserveState = false,
}: {
    page: Paginated<unknown>;
    label: string;
    /** The buttons' text: by default the consoles' newest-first order. */
    previous?: string;
    next?: string;
    /** Keep what the page holds that is not in its address, like a selection, when turning pages. */
    preserveState?: boolean;
}) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <nav
            aria-label={label}
            className="flex flex-wrap items-center justify-between gap-4"
        >
            <p className="text-sm text-muted-foreground">
                Página {page.current_page} de {page.last_page}
            </p>
            <div className="flex gap-2">
                {page.prev_page_url !== null && (
                    <Button variant="outline" asChild>
                        <Link
                            href={page.prev_page_url}
                            preserveState={preserveState}
                        >
                            {previous}
                        </Link>
                    </Button>
                )}
                {page.next_page_url !== null && (
                    <Button variant="outline" asChild>
                        <Link
                            href={page.next_page_url}
                            preserveState={preserveState}
                        >
                            {next}
                        </Link>
                    </Button>
                )}
            </div>
        </nav>
    );
}
