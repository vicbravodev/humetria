import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';

/** A figure of the overview: its title, the number, a sentence and the way into the list behind it. */
export default function OverviewCard({
    title,
    figure,
    caption,
    href,
    action,
    children,
}: {
    title: string;
    figure: string;
    caption: string;
    href: NonNullable<InertiaLinkProps['href']>;
    action: string;
    children?: ReactNode;
}) {
    return (
        <section className="grid content-start gap-3 card p-5">
            <h2 className="text-base font-semibold">{title}</h2>
            <p className="text-3xl font-semibold tracking-[-0.01em] tabular-nums">
                {figure}
            </p>
            <p className="text-sm text-muted-foreground">{caption}</p>
            {children}
            <Link
                href={href}
                className="-mb-1 inline-flex min-h-11 w-fit items-center gap-1.5 text-sm font-medium text-primary underline-offset-4 hover:underline"
            >
                {action}
                <ArrowRight aria-hidden className="size-4" />
            </Link>
        </section>
    );
}
