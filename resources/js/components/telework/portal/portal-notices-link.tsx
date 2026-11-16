import { Link } from '@inertiajs/react';
import { ChevronRight, MessageSquareWarning } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import { show } from '@/routes/portal/notices';

/** The way to the person's written notices (6.3, 6.7, 6.9): one big card, as the tools of their start. */
export default function PortalNoticesLink({ slug }: { slug: string }) {
    return (
        <Link
            href={show.url(slug)}
            className="flex min-h-14 items-center gap-3.5 card p-4 hover:bg-accent/50"
        >
            <IconTile icon={MessageSquareWarning} />
            <span className="flex min-w-0 flex-1 flex-col gap-1">
                <span className="text-base font-semibold">
                    Sus avisos por escrito
                </span>
                <span className="text-base text-muted-foreground">
                    Cambio de domicilio, riesgo de trabajo o alteración de sus
                    condiciones.
                </span>
            </span>
            <ChevronRight
                aria-hidden="true"
                className="size-5 shrink-0 text-muted-foreground"
            />
        </Link>
    );
}
