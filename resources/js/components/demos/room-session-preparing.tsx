import { Check } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { preparationPhase } from '@/lib/demos/room';
import { cn } from '@/lib/utils';
import type { DemoRoomSession } from '@/types';

/**
 * The session being prepared, phase by phase from the step its run reached; or ending, while its reset wipes what the
 * audience left. The room asks again meanwhile (RoomPoll).
 */
export default function RoomSessionPreparing({
    session,
    step,
}: {
    session: DemoRoomSession;
    step: string | null;
}) {
    const ending = session.status === 'ending';
    const phase = preparationPhase(step);
    const center =
        session.client === null
            ? 'Sala de demostración'
            : `Sala · ${session.client}`;
    const phases = [
        'Reiniciar la demo',
        'Contar la historia de la empresa demo',
        `Crear el centro «${center}» y dejar lista la sala`,
    ];

    return (
        <section
            aria-labelledby="room-session"
            className="flex max-w-3xl flex-col gap-4 card p-5 md:p-6"
        >
            <div className="flex flex-wrap items-center gap-3">
                <h2 id="room-session" className="text-lg font-semibold">
                    {ending ? 'Terminando la sesión' : 'Preparando la sesión'}
                </h2>
                <Badge>{ending ? 'Terminando' : 'Preparando'}</Badge>
            </div>
            {ending ? (
                <p role="status" className="flex items-center gap-3 text-sm">
                    <Spinner />
                    La demo se reinicia y borra lo que dejó el público.
                </p>
            ) : (
                <ol role="status" className="flex flex-col gap-2.5">
                    {phases.map((label, index) => (
                        <li
                            key={label}
                            className={cn(
                                'flex items-center gap-3 text-sm',
                                index > phase && 'text-muted-foreground',
                                index === phase && 'font-semibold',
                            )}
                        >
                            {index < phase && (
                                <Check
                                    aria-hidden
                                    className="size-4 text-primary"
                                />
                            )}
                            {index === phase && <Spinner />}
                            {index > phase && (
                                <span
                                    aria-hidden
                                    className="size-4 rounded-full border"
                                />
                            )}
                            {label}
                        </li>
                    ))}
                </ol>
            )}
            <p className="text-meta text-muted-foreground">
                Tarda alrededor de medio minuto. Puedes dejar esta página: sigue
                en segundo plano.
            </p>
        </section>
    );
}
