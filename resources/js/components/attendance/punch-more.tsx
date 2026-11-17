import { ChevronRight } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * A section that opens to read, below what the screen is for (board «Asistencia: marcar, acciones en 2×2»): its icon and
 * its title; the whole text inside, nothing taken out.
 */
export default function PunchMore({
    icon: Icon,
    title,
    children,
}: {
    icon: LucideIcon;
    title: string;
    children: ReactNode;
}) {
    return (
        <details className="group card">
            <summary className="flex min-h-14 cursor-pointer list-none items-center gap-3 rounded-[inherit] px-4 text-base font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden">
                <Icon aria-hidden className="size-5 shrink-0 text-primary" />
                {title}
                <ChevronRight
                    aria-hidden
                    className="ml-auto size-5 shrink-0 text-muted-foreground transition-transform duration-200 ease-out group-open:rotate-90 motion-reduce:transition-none"
                />
            </summary>
            <div className="px-4 pb-4 text-base text-muted-foreground">
                {children}
            </div>
        </details>
    );
}
