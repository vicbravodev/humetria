import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ChevronRight } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';

/**
 * One task of the person's Ley Silla page (board SillaHub): a big card (at least 64 px, for a thumb) that opens its own
 * screen, with what it is, one line, an optional tag (voluntary, anonymous) and a badge when it waits for them.
 */
export default function PortalTaskCard({
    task,
}: {
    task: {
        href: string;
        icon: LucideIcon;
        title: string;
        description: string;
        tag?: { icon: LucideIcon; label: string };
        waiting?: boolean;
    };
}) {
    return (
        <li>
            <Link
                href={task.href}
                className="flex min-h-16 items-center gap-3.5 card p-4 transition-[background-color,scale] duration-150 ease-out hover:bg-accent/50 active:scale-[0.99]"
            >
                <IconTile icon={task.icon} />
                <span className="flex min-w-0 flex-1 flex-col">
                    <span className="text-base font-semibold">
                        {task.title}
                    </span>
                    <span className="text-base text-muted-foreground">
                        {task.description}
                    </span>
                    {task.tag !== undefined && (
                        <span className="mt-1 flex items-center gap-1 text-sm text-muted-foreground">
                            <task.tag.icon aria-hidden className="size-3.5" />
                            {task.tag.label}
                        </span>
                    )}
                </span>
                {task.waiting === true && (
                    <span className="rounded-full bg-primary px-2.5 py-0.5 text-sm font-semibold text-primary-foreground">
                        1<span className="sr-only"> pendiente</span>
                    </span>
                )}
                <ChevronRight
                    aria-hidden
                    className="size-5 shrink-0 text-muted-foreground"
                />
            </Link>
        </li>
    );
}
