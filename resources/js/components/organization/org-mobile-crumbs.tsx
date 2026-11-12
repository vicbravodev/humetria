import { ChevronRight } from 'lucide-react';
import type { Crumb } from '@/lib/org-chart/mobile';

/** The way from the company down to the person on screen: each step before the last takes the phone back to it. */
export default function OrgMobileCrumbs({
    crumbs,
    onGo,
}: {
    crumbs: Crumb[];
    onGo: (id: string | null) => void;
}) {
    return (
        <nav aria-label="Línea de mando">
            <ol className="flex flex-wrap items-center gap-x-1 gap-y-0.5 text-meta">
                {crumbs.map((crumb, index) => {
                    const last = index === crumbs.length - 1;

                    return (
                        <li
                            key={crumb.id ?? 'company'}
                            className="flex items-center gap-1"
                        >
                            {index > 0 && (
                                <ChevronRight
                                    aria-hidden
                                    className="size-3.5 text-muted-foreground"
                                />
                            )}
                            {last ? (
                                <span
                                    aria-current="page"
                                    className="font-semibold"
                                >
                                    {crumb.label}
                                </span>
                            ) : (
                                <button
                                    type="button"
                                    onClick={() => onGo(crumb.id)}
                                    className="relative inline-flex min-h-11 items-center text-primary before:absolute before:-inset-x-1 before:inset-y-0 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                >
                                    {crumb.label}
                                </button>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
