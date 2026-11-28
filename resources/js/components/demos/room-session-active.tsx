import { Link, router } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
import RoomSessionEnd from '@/components/demos/room-session-end';
import ProgressBar from '@/components/humetria/progress-bar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { sessionTitle } from '@/lib/demos/room';
import { impersonate } from '@/routes/companies';
import type { DemoRoomSession } from '@/types';

/**
 * The session under way: where the guide goes on, each tour's progress, «Terminar sesión» and «Entrar y seguir el guía»,
 * which enters the demo as its owner right on the guide's step.
 */
export default function RoomSessionActive({
    session,
    companyId,
}: {
    session: DemoRoomSession;
    companyId: string;
}) {
    const next = session.next;

    return (
        <section
            aria-labelledby="room-session"
            className="flex max-w-3xl flex-col gap-5 card p-5 md:p-6"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-1">
                    <h2 id="room-session" className="text-lg font-semibold">
                        {sessionTitle(session.client)}
                    </h2>
                    <p className="text-meta text-pretty text-muted-foreground">
                        {next === null ? (
                            'Terminaste los recorridos de esta sesión.'
                        ) : (
                            <>
                                Sigue:{' '}
                                <strong className="font-semibold text-foreground">
                                    {next.title}
                                </strong>
                                {` · ${next.tour}, paso ${next.index} de ${next.total}`}
                            </>
                        )}
                    </p>
                </div>
                <Badge variant="success">Sesión en curso</Badge>
            </div>
            <ul className="flex flex-col gap-3">
                {session.tours.map((tour) => (
                    <li
                        key={tour.key}
                        className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1.5 sm:grid-cols-[10rem_minmax(0,1fr)_4rem]"
                    >
                        <span className="text-sm">{tour.title}</span>
                        <span className="text-meta text-muted-foreground tabular-nums sm:order-last sm:text-right">
                            {`${tour.done} de ${tour.total}`}
                        </span>
                        <div className="col-span-2 sm:col-span-1">
                            <ProgressBar
                                value={tour.done}
                                max={tour.total}
                                label={`${tour.title}: ${tour.done} de ${tour.total} pasos`}
                            />
                        </div>
                    </li>
                ))}
            </ul>
            <div className="flex flex-wrap justify-between gap-2 border-t pt-4">
                <RoomSessionEnd client={session.client} />
                {/* Another person's session: nothing prefetched as the staff member carries over. */}
                <Button asChild className="max-sm:w-full">
                    <Link
                        href={impersonate(companyId)}
                        data={
                            next === null
                                ? { as: 'owner' }
                                : { as: 'owner', to: next.href }
                        }
                        as="button"
                        onClick={() => router.flushAll()}
                    >
                        <LogIn aria-hidden />
                        Entrar y seguir el guía
                    </Link>
                </Button>
            </div>
        </section>
    );
}
