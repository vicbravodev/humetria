import type { ReactNode } from 'react';

/** The title of a screen of a person and, under it, what it is about (boards P1 to P4): the h1 of the page. */
export default function PersonHeading({
    title,
    children,
}: {
    title: string;
    children?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2">
            <h1 className="text-2xl leading-8 font-semibold tracking-tight text-balance">
                {title}
            </h1>
            {children !== undefined && (
                <p className="text-muted-foreground">{children}</p>
            )}
        </div>
    );
}
