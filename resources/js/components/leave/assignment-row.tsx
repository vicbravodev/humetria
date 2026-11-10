import type { ReactNode } from 'react';

/** One row of the assignments: whom it is for, and the choice of their policy. */
export default function AssignmentRow({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <li className="flex flex-wrap items-center justify-between gap-3 py-3">
            <span className="min-w-0 text-base font-medium">{label}</span>
            {children}
        </li>
    );
}
