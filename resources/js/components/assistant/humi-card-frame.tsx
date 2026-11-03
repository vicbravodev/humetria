import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';

/** The frame of a card in an answer: its small title, its rows, a note and where to see it in full in the panel. */
export default function HumiCardFrame({
    title,
    note,
    link,
    children,
}: {
    title: string;
    note: string | null;
    link: string | null;
    children: ReactNode;
}) {
    return (
        <section
            className="humi-enter flex flex-col gap-3 rounded-lg border bg-card p-4"
            aria-label={title}
        >
            <h3 className="text-caption font-medium tracking-wide text-muted-foreground uppercase">
                {title}
            </h3>
            {children}
            {note !== null && (
                <p className="text-sm text-muted-foreground">{note}</p>
            )}
            {link !== null && (
                <Link
                    href={link}
                    className="inline-flex items-center gap-1 border-t pt-3 text-sm font-medium text-primary hover:underline focus-visible:outline-2 focus-visible:outline-ring"
                >
                    Ver en el panel
                    <ArrowRight aria-hidden className="size-4" />
                </Link>
            )}
        </section>
    );
}
