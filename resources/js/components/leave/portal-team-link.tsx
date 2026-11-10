import { Link } from '@inertiajs/react';
import { Users } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import { countOf, plural } from '@/lib/plural';
import { show } from '@/routes/portal/vacations/team';

/** The way to the requests of the person's team, while any waits for their answer. */
export default function PortalTeamLink({
    token,
    pending,
}: {
    token: string;
    pending: number;
}) {
    if (pending === 0) {
        return null;
    }

    return (
        <Link
            href={show.url({ token })}
            className="flex items-start gap-3.5 card p-4 hover:bg-accent/50"
        >
            <IconTile icon={Users} />
            <span className="flex flex-col gap-1">
                <span className="text-base font-semibold">
                    Solicitudes de su equipo
                </span>
                <span className="text-base text-muted-foreground">
                    {countOf(pending, 'solicitud', 'solicitudes')}{' '}
                    {plural(pending, 'espera', 'esperan')} su respuesta.
                </span>
            </span>
        </Link>
    );
}
