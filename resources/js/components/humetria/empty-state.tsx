import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/** What a list shows before it has anything: an icon of what will be here, a title and what makes it appear. */
export default function EmptyState({
    icon: Icon,
    title,
    children,
}: {
    icon: LucideIcon;
    title: string;
    children: ReactNode;
}) {
    return (
        <section
            data-slot="empty-state"
            className="flex flex-col items-center rounded-xl border border-dashed bg-card px-6 py-12 text-center"
        >
            <span className="mb-4 flex size-12 items-center justify-center rounded-xl bg-accent text-primary">
                <Icon aria-hidden className="size-6" />
            </span>
            <h2 className="text-xl leading-7 font-semibold tracking-[-0.01em]">
                {title}
            </h2>
            <p className="mt-1 max-w-md text-muted-foreground">{children}</p>
        </section>
    );
}
