import { usePoll } from '@inertiajs/react';
import { Send, X } from 'lucide-react';
import { useState } from 'react';
import ProgressBar from '@/components/humetria/progress-bar';
import { Button } from '@/components/ui/button';
import type { CampaignSending } from '@/types';

/**
 * How the last batch of survey links goes while the queue sends it, floating at the bottom right (pantalla
 * Eval-Activa). The page asks again every few seconds and this goes away once every link of the batch was sent or given
 * up on; hiding it only hides it.
 */
export default function SendingProgress({
    sending,
}: {
    sending: CampaignSending;
}) {
    const [hidden, setHidden] = useState(false);

    usePoll(3000, {
        only: [
            'sending',
            'people',
            'standings',
            'instruments',
            'areas',
            'answersByDay',
        ],
    });

    if (hidden) {
        return null;
    }

    return (
        <div
            role="status"
            className="fixed right-4 bottom-32 z-20 flex w-[min(340px,calc(100vw-2rem))] flex-col gap-2.5 rounded-xl border bg-popover px-4 py-3.5 text-popover-foreground shadow-raised md:right-8 md:bottom-24"
        >
            <div className="flex items-center gap-2.5">
                <Send aria-hidden="true" className="size-4 text-primary" />
                <p className="grow text-sm font-medium tabular-nums">
                    Enviando invitaciones · {sending.done} de {sending.total}
                </p>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    aria-label="Ocultar"
                    onClick={() => setHidden(true)}
                >
                    <X aria-hidden="true" />
                </Button>
            </div>
            <ProgressBar
                value={sending.done}
                max={sending.total}
                label="Invitaciones enviadas"
            />
        </div>
    );
}
