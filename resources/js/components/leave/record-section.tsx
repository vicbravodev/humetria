import type { ReactNode } from 'react';

/** A titled block of the record of a person. */
export default function RecordSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="flex flex-col gap-4">
            <h2 className="text-lg font-semibold">{title}</h2>
            {children}
        </section>
    );
}
