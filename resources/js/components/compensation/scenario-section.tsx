import type { ReactNode } from 'react';

/** A block of the scenario editor: a card with what it asks for and, when it helps, why; then its fields. */
export default function ScenarioSection({
    title,
    description,
    action,
    children,
}: {
    title: string;
    description?: string;
    /** A control at the right of the title, like «Revisar». */
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-5 card p-6">
            <header className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                <div className="grid min-w-0 gap-1">
                    <h2 className="text-lg leading-6 font-semibold">{title}</h2>
                    {description !== undefined && (
                        <p className="max-w-[640px] text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {action !== undefined && (
                    <div className="shrink-0">{action}</div>
                )}
            </header>
            {children}
        </section>
    );
}
