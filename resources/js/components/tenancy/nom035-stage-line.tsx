import { Link } from '@inertiajs/react';
import type { RouteRow } from '@/types';

/** What a stage ahead asks for in one line (board V2-Ruta-N035): where it stands, and its way in for whoever may. */
export default function Nom035StageLine({ row }: { row: RouteRow }) {
    if (row.detail === null && row.href === null) {
        return null;
    }

    return (
        <p className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
            {row.detail !== null && <span>{row.detail}</span>}
            {row.href !== null && row.action !== null && (
                <Link
                    href={row.href}
                    className="touch-hit font-medium text-primary hover:underline"
                >
                    {row.action}
                    <span className="sr-only">: {row.title}</span>
                </Link>
            )}
        </p>
    );
}
