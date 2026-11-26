import { CircleAlert } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Something on the page needs a look, without alarm (pantallas: Empleados, Eval-Borrador): "3 personas no tienen
 * correo ni celular". The sentence starts with the fact in bold; the action that fixes it goes at the right, or under it
 * when the row is too narrow for both (the sentence never shrinks below 12rem).
 */
export default function Notice({
    children,
    action,
}: {
    children: ReactNode;
    action?: ReactNode;
}) {
    return (
        <div
            role="status"
            data-slot="notice"
            className="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-xl bg-warning px-5 py-3.5 text-warning-foreground"
        >
            <CircleAlert aria-hidden className="size-5 shrink-0" />
            <p className="min-w-0 flex-[1_1_12rem] text-meta [&_strong]:font-semibold">
                {children}
            </p>
            {action}
        </div>
    );
}
