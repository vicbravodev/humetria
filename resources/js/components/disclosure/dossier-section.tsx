import type { ReactNode } from 'react';

/** A block of the dossier: a heading and what is recorded under it. It breaks as a unit when printed. */
export default function DossierSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section
            aria-label={title}
            className="flex break-inside-avoid flex-col gap-2"
        >
            <h2 className="text-lg font-semibold">{title}</h2>
            <div className="flex flex-col gap-1.5 text-base">{children}</div>
        </section>
    );
}
