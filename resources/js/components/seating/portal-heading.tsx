import type { ReactNode } from 'react';

/** The top of a Ley Silla screen of the person: the small line of context, the title and one line of what it is for. */
export default function PortalHeading({
    eyebrow,
    title,
    children,
}: {
    eyebrow: string;
    title: string;
    children?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <p className="text-caption font-medium tracking-wide text-primary uppercase">
                {eyebrow}
            </p>
            <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                {title}
            </h1>
            {children !== undefined && (
                <p className="text-muted-foreground">{children}</p>
            )}
        </div>
    );
}
