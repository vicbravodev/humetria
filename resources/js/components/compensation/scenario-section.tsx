import type { ReactNode } from 'react';

/** A block of the scenario editor: a card with what it asks for and why, then its fields. */
export default function ScenarioSection({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-5 card p-6">
            <header className="grid gap-1">
                <h2 className="text-lg leading-6 font-semibold">{title}</h2>
                <p className="max-w-[640px] text-muted-foreground">
                    {description}
                </p>
            </header>
            {children}
        </section>
    );
}
