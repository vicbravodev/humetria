import type { ReactNode } from 'react';

/** A numbered part of the report, with the incise of num. 7.7 it answers. */
export default function ReportSection({
    number,
    title,
    incise,
    children,
}: {
    number: number;
    title: string;
    incise: string;
    children: ReactNode;
}) {
    return (
        <section className="grid break-inside-avoid-page gap-3">
            <h2 className="text-xl font-semibold">
                {number}. {title}
                <span className="ml-2 text-sm font-normal text-muted-foreground">
                    (7.7 {incise})
                </span>
            </h2>
            {children}
        </section>
    );
}
