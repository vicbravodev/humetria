import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

/** The icon and the name of a tab of the portal, and how many things wait on «Pendientes» (a badge over its icon). */
export default function PortalTabContent({
    icon: Icon,
    label,
    badge,
    here,
}: {
    icon: LucideIcon;
    label: string;
    /** How many things wait in it; 0 draws nothing. */
    badge: number;
    here: boolean;
}) {
    return (
        <>
            <span className="relative flex">
                <Icon aria-hidden className="size-5" />
                {badge > 0 && (
                    <span
                        className={cn(
                            'absolute -top-2 -right-3.5 min-w-5 rounded-full px-1.5 text-center text-caption leading-5 font-semibold',
                            here
                                ? 'bg-primary-foreground text-primary'
                                : 'bg-primary text-primary-foreground',
                        )}
                    >
                        {badge}
                        <span className="sr-only">
                            {badge === 1 ? ' pendiente' : ' pendientes'}
                        </span>
                    </span>
                )}
            </span>
            {label}
        </>
    );
}
