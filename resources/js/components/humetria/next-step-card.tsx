import type { ReactNode } from 'react';

/**
 * "Siguiente paso recomendado" (board V2-Panel): the one step to take now, what it is about, how far it reaches and its
 * button. It sits on the accent so it reads as the page's next move.
 */
export default function NextStepCard({
    title,
    children,
    meta,
    action,
}: {
    title: string;
    children: ReactNode;
    meta: string;
    action: ReactNode;
}) {
    return (
        <section
            aria-labelledby="next-step"
            className="flex flex-col gap-3 rounded-xl bg-accent p-6"
        >
            <p className="text-sm font-semibold text-primary">
                Siguiente paso recomendado
            </p>
            <h2 id="next-step" className="text-xl leading-7 font-semibold">
                {title}
            </h2>
            <p className="text-sm">{children}</p>
            <p className="text-sm text-muted-foreground">{meta}</p>
            <div>{action}</div>
        </section>
    );
}
