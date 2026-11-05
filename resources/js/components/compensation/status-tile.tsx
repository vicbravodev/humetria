import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { CircleCheck, CircleDashed } from 'lucide-react';
import type { StatusItem } from '@/lib/compensation/start';
import { cn } from '@/lib/utils';

/** One datum of the contract as a tile (board V4-Comp-Inicio): done or missing, what it is, how much there is and what it lacks. */
export default function StatusTile({
    item,
    icon: Icon,
    href,
}: {
    item: StatusItem;
    icon: LucideIcon;
    href: NonNullable<InertiaLinkProps['href']>;
}) {
    return (
        <Link
            href={href}
            className="grid content-start gap-1 card px-4 py-4 transition-colors hover:border-foreground/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            <span className="mb-2 flex items-center justify-between">
                {item.problem ? (
                    <CircleDashed
                        aria-label="Le falta algo"
                        className="size-5 text-risk-alto-fg"
                    />
                ) : (
                    <CircleCheck
                        aria-label="Al día"
                        className="size-5 fill-primary text-primary-foreground"
                    />
                )}
                <Icon aria-hidden className="size-5 text-muted-foreground" />
            </span>
            <span className="text-sm text-muted-foreground">{item.label}</span>
            <span className="font-semibold">{item.value}</span>
            <span
                className={cn(
                    'text-sm',
                    item.problem
                        ? 'text-risk-alto-fg'
                        : 'text-muted-foreground',
                )}
            >
                {item.note}
            </span>
        </Link>
    );
}
