import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/** One of the ways the disclosure reaches the people (board V4-Difusion): its icon and name, what it is and what to do. */
export default function ReachCard({
    icon: Icon,
    title,
    children,
    actions,
}: {
    icon: LucideIcon;
    title: string;
    children: ReactNode;
    actions: ReactNode;
}) {
    return (
        <section className="flex flex-col gap-3 card p-5 md:p-6">
            <h2 className="flex items-center gap-2.5 text-lg font-semibold">
                <Icon aria-hidden className="size-5 text-muted-foreground" />
                {title}
            </h2>
            <div className="flex-1 text-sm text-muted-foreground">
                {children}
            </div>
            <div className="flex flex-wrap items-center gap-3">{actions}</div>
        </section>
    );
}
