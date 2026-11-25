import type { ReactNode } from 'react';

/** One part of a company's page, the place each of its tabs jumps to. */
export default function CompanySection({
    id,
    title,
    shown = true,
    children,
}: {
    id: string;
    title: string;
    /** Off when what it holds opens with its own heading: the title stays for screen readers only. */
    shown?: boolean;
    children: ReactNode;
}) {
    return (
        <section
            id={id}
            aria-labelledby={`${id}-title`}
            className="grid scroll-mt-16 gap-4"
        >
            <h2
                id={`${id}-title`}
                className={shown ? 'text-lg font-semibold' : 'sr-only'}
            >
                {title}
            </h2>
            {children}
        </section>
    );
}
