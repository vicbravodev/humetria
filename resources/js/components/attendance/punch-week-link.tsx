import { Link } from '@inertiajs/react';
import { CalendarDays, ChevronRight } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';

/** «Mi semana», near the top of Marcar where it is seen, not at the end of the page. */
export default function PunchWeekLink({ href }: { href: string }) {
    return (
        <Link
            href={href}
            className="flex min-h-16 items-center gap-3.5 card p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            <IconTile icon={CalendarDays} />
            <span className="flex min-w-0 flex-1 flex-col text-base">
                <span className="font-medium">Mi semana</span>
                <span className="text-muted-foreground">
                    Vea sus marcas y sus horas
                </span>
            </span>
            <ChevronRight
                aria-hidden
                className="size-5 shrink-0 text-muted-foreground"
            />
        </Link>
    );
}
