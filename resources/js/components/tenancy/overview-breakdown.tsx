import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';

/** The parts of a figure, each a link to its list with how many it holds; the ones with none are left out. */
export default function OverviewBreakdown({
    label,
    rows,
}: {
    label: string;
    rows: {
        label: string;
        count: number;
        href: NonNullable<InertiaLinkProps['href']>;
    }[];
}) {
    const shown = rows.filter((row) => row.count > 0);

    if (shown.length === 0) {
        return null;
    }

    return (
        <ul aria-label={label} className="grid gap-px text-sm">
            {shown.map((row) => (
                <li key={row.label}>
                    <Link
                        href={row.href}
                        className="flex min-h-11 items-center justify-between gap-3 rounded-md px-2 hover:bg-muted"
                    >
                        <span>{row.label}</span>
                        <span className="text-muted-foreground tabular-nums">
                            {row.count}
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}
