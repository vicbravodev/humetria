import { Spinner } from '@/components/ui/spinner';

/** The step the run going on is at; the room asks again while it runs (RoomPoll). */
export default function RoomProgress({ step }: { step: string | null }) {
    return (
        <div role="status" className="flex items-center gap-3 card px-5 py-3.5">
            <Spinner />
            <p className="text-meta">
                {step === null
                    ? 'Empezando a preparar la demo…'
                    : `Preparando: ${step}.`}{' '}
                <span className="text-muted-foreground">
                    Puedes dejar esta página: sigue en segundo plano.
                </span>
            </p>
        </div>
    );
}
