import Initials from '@/components/humetria/initials';
import type { PortalTeamManager as Manager } from '@/types';

/** Whom the person reports to, or what to do when nobody is registered. */
export default function PortalTeamManager({
    manager,
}: {
    manager: Manager | null;
}) {
    return (
        <section aria-labelledby="team-manager" className="grid gap-3">
            <h2 id="team-manager" className="text-xl font-semibold">
                Su jefe directo
            </h2>
            {manager === null ? (
                <p className="card p-4 text-base text-muted-foreground">
                    No tiene un jefe directo registrado. Si esto no es correcto,
                    avise a Recursos Humanos.
                </p>
            ) : (
                <div className="flex items-center gap-3.5 card p-4">
                    <Initials name={manager.name} className="size-10 text-sm" />
                    <p className="flex min-w-0 flex-col">
                        <span className="text-base font-semibold">
                            {manager.name}
                        </span>
                        <span className="text-base text-muted-foreground">
                            {manager.job_title}
                        </span>
                    </p>
                </div>
            )}
        </section>
    );
}
