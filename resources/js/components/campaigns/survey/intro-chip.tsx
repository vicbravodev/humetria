import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/** A fact of the first screen as a chip: how long, until when. */
export default function IntroChip({
    icon: Icon,
    children,
}: {
    icon: LucideIcon;
    children: ReactNode;
}) {
    return (
        <span className="inline-flex min-h-9 items-center gap-1.5 rounded-full bg-muted px-3 text-sm font-medium">
            <Icon aria-hidden="true" className="size-4 text-muted-foreground" />
            {children}
        </span>
    );
}
