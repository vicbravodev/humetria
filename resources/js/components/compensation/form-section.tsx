import type { ReactNode } from 'react';

/** A group of a Compensación form, under a quiet caption: "Qué es", "A quién aplica". */
export default function FormSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-4">
            <h3 className="text-caption font-medium text-muted-foreground">
                {title}
            </h3>
            {children}
        </section>
    );
}
