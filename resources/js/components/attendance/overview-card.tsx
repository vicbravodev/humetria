import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { cn } from '@/lib/utils';

/**
 * One figure of the overview, with what it counts and the way to see who (pantalla NOM035: cards with a figure). A figure
 * of this same page (`#…`) is a plain anchor; any other opens its list.
 */
export default function OverviewCard({
    label,
    value,
    hint,
    href,
    alert = false,
}: {
    label: string;
    value: number;
    hint: string;
    href: NonNullable<InertiaLinkProps['href']>;
    /** A figure that asks for RH's attention when it is not zero. */
    alert?: boolean;
}) {
    const loud = alert && value > 0;
    const className = cn(
        'flex flex-col gap-1 card px-5 py-[18px] transition-colors duration-150 ease-out hover:bg-accent/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
        loud && 'border-destructive',
    );
    const body = (
        <>
            <span
                className={cn(
                    'text-sm leading-5',
                    loud ? 'text-destructive' : 'text-muted-foreground',
                )}
            >
                {label}
            </span>
            <span className="text-3xl leading-[38px] font-semibold tracking-[-0.02em] tabular-nums">
                {value.toLocaleString('es-MX')}
            </span>
            <span className="text-caption text-pretty text-muted-foreground">
                {hint}
            </span>
        </>
    );

    return typeof href === 'string' && href.startsWith('#') ? (
        <a href={href} className={className}>
            {body}
        </a>
    ) : (
        <Link href={href} preserveScroll className={className}>
            {body}
        </Link>
    );
}
