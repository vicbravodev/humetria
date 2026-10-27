import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { DemoRequestRow, Paginated } from '@/types';

export default function DemoRequestsPagination({
    page,
}: {
    page: Paginated<DemoRequestRow>;
}) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <nav
            aria-label="Páginas de solicitudes"
            className="flex flex-wrap items-center justify-between gap-4"
        >
            <p className="text-sm text-muted-foreground">
                Página {page.current_page} de {page.last_page}
            </p>
            <div className="flex gap-2">
                {page.prev_page_url !== null && (
                    <Button variant="outline" asChild>
                        <Link href={page.prev_page_url}>Ver más recientes</Link>
                    </Button>
                )}
                {page.next_page_url !== null && (
                    <Button variant="outline" asChild>
                        <Link href={page.next_page_url}>Ver más antiguas</Link>
                    </Button>
                )}
            </div>
        </nav>
    );
}
