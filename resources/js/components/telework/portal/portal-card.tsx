import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import IconTile from '@/components/humetria/icon-tile';

/**
 * One thing the person has to look at in their portal: its icon, what it is, where or when it comes from, and below
 * what to do about it. Text at 16 px or more, as everywhere the person reads (they are not used to the panel).
 */
export default function PortalCard({
    icon,
    title,
    detail,
    children,
}: {
    icon: LucideIcon;
    title: ReactNode;
    detail: ReactNode;
    children?: ReactNode;
}) {
    return (
        <li className="flex items-start gap-3.5 rounded-xl border bg-card p-4">
            <IconTile icon={icon} />
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <p className="text-base font-semibold">{title}</p>
                <p className="text-base text-muted-foreground">{detail}</p>
                {children}
            </div>
        </li>
    );
}
