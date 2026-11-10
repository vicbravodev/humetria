import { Link } from '@inertiajs/react';
import { Network } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import { show } from '@/routes/portal/team';

/** The way into «Mi equipo»: the person's manager, their peers and whoever reports to them. */
export default function PortalTeamLink({ token }: { token: string }) {
    return (
        <section aria-labelledby="portal-team" className="grid gap-3">
            <h2 id="portal-team" className="text-xl font-semibold">
                Su equipo
            </h2>
            <Link
                href={show.url({ token })}
                className="flex items-start gap-3.5 card p-4 hover:bg-accent/50"
            >
                <IconTile icon={Network} />
                <span className="flex flex-col gap-1">
                    <span className="text-base font-semibold">Mi equipo</span>
                    <span className="text-base text-muted-foreground">
                        Su jefe directo, sus compañeros y quienes le reportan.
                    </span>
                </span>
            </Link>
        </section>
    );
}
