import { CircleAlert } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Something on the page needs a look, without alarm (pantallas: Empleados, Eval-Borrador): "3 personas no tienen
 * correo ni celular". The sentence starts with the fact in bold; the action that fixes it goes at the right.
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
            <p className="min-w-0 flex-1 text-[15px] leading-[22px] [&_strong]:font-semibold">
                {children}
            </p>
            {action}
        </div>
    );
}
