import type { ReactNode } from 'react';

/**
 * «Antes de seguir» / «Falta 1 cosa…» (boards V4-Quejas-Bandeja and V4-Quejas-Canal): the one thing to do before anything
 * else, with a terracotta edge, what it is about and its button.
 */
export default function BeforeCard({
    eyebrow,
    title,
    children,
    action,
}: {
    eyebrow: string;
    title: string;
    children: ReactNode;
    action: ReactNode;
}) {
    return (
        <section
            aria-labelledby="before-card"
            className="flex flex-wrap items-center justify-between gap-x-8 gap-y-4 card border-l-4 border-l-risk-alto p-6"
        >
            <div className="flex max-w-[760px] min-w-0 flex-col gap-1.5">
                <p className="text-caption font-semibold tracking-[0.06em] text-risk-alto-fg uppercase">
                    {eyebrow}
                </p>
                <h2
                    id="before-card"
                    className="text-xl leading-7 font-semibold"
                >
                    {title}
                </h2>
                <div className="text-pretty text-muted-foreground">
                    {children}
                </div>
            </div>
            {action}
        </section>
    );
}
