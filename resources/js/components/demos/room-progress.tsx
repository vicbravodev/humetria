import { usePoll } from '@inertiajs/react';
import { Spinner } from '@/components/ui/spinner';

/** The run going on, asked again every few seconds until it ends: then the page has the demo as it was left. */
export default function RoomProgress({ step }: { step: string | null }) {
    usePoll(3000, { only: ['run', 'company', 'prepared', 'complaints'] });

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
