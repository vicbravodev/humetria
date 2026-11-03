import type { ReactNode } from 'react';

/**
 * One of the things numeral 5.7 asks to tell the people, on the public page: what it is (its inciso and name) and what the
 * company says of it. Without content it says so, instead of hiding: the people see what is still missing.
 */
export default function PublicBlock({
    id,
    inciso,
    title,
    children,
}: {
    id: string;
    inciso: string;
    title: string;
    children: ReactNode;
}) {
    return (
        <section aria-labelledby={id} className="flex flex-col gap-4 card p-5">
            <h2 id={id} className="text-xl leading-7 font-semibold">
                <span className="mr-2 text-base font-medium text-muted-foreground">
                    {inciso})
                </span>
                {title}
            </h2>
            {children}
        </section>
    );
}
