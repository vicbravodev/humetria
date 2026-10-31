import type { ReactNode } from 'react';

/** A group of the employee form, under a quiet caption: "Persona", "Dónde trabaja". */
export default function EmployeeFormSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-4">
            <h3 className="text-[13px] leading-[18px] font-medium text-muted-foreground">
                {title}
            </h3>
            {children}
        </section>
    );
}
