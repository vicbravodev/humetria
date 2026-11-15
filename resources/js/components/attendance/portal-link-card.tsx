import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ChevronRight } from 'lucide-react';
import ToneTile from '@/components/attendance/tone-tile';

/** One of the ways «Mi jornada» goes on, as a whole tappable card: its icon, what it is for and an arrow. */
export default function PortalLinkCard({
    href,
    icon,
    title,
    children,
}: {
    href: string;
    icon: LucideIcon;
    title: string;
    children: string;
}) {
    return (
        <Link
            href={href}
            className="touch-hit flex items-center gap-4 card p-5 transition-colors hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
        >
            <ToneTile icon={icon} />
            <span className="flex min-w-0 flex-1 flex-col">
                <span className="text-lg font-semibold">{title}</span>
                <span className="text-base text-muted-foreground">
                    {children}
                </span>
            </span>
            <ChevronRight
                aria-hidden
                className="size-5 shrink-0 text-muted-foreground"
            />
        </Link>
    );
}
