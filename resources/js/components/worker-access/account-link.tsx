import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ChevronRight } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';

/** A card of «Cuenta» that leads to another page: its icon, what it is and a line saying what it does. */
export default function AccountLink({
    href,
    icon,
    title,
    detail,
}: {
    href: string;
    icon: LucideIcon;
    title: string;
    detail: string;
}) {
    return (
        <Link
            href={href}
            className="flex min-h-16 items-center gap-3.5 card p-4 transition-[background-color,scale] duration-150 ease-out hover:bg-accent/50 active:scale-[0.99]"
        >
            <IconTile icon={icon} />
            <span className="flex min-w-0 flex-1 flex-col">
                <span className="text-base font-semibold">{title}</span>
                <span className="text-base text-muted-foreground">
                    {detail}
                </span>
            </span>
            <ChevronRight
                aria-hidden="true"
                className="size-5 shrink-0 text-muted-foreground"
            />
        </Link>
    );
}
