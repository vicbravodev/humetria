import { Check } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * One thing to review before launching (pantalla Eval-Borrador): a check when it is ready as it is, its number when it
 * still asks for a choice.
 */
export default function ReviewStep({
    number,
    title,
    children,
}: {
    /** Shown while the step asks for a choice; without it the step is ready. */
    number?: number;
    title: string;
    children: ReactNode;
}) {
    return (
        <li className="grid grid-cols-[28px_minmax(0,1fr)] gap-4 border-t py-5 first:border-t-0">
            {number === undefined ? (
                <span className="inline-flex size-7 items-center justify-center rounded-full bg-primary text-primary-foreground">
                    <Check
                        aria-hidden="true"
                        className="size-4"
                        strokeWidth={2.5}
                    />
                </span>
            ) : (
                <span className="inline-flex size-7 items-center justify-center rounded-full border-[1.5px] border-primary text-[13px] font-semibold text-primary">
                    {number}
                </span>
            )}
            <div className="flex min-w-0 flex-col gap-2.5">
                <h3 className="font-semibold">{title}</h3>
                {children}
            </div>
        </li>
    );
}
