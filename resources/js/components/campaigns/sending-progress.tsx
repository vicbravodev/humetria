import { usePoll } from '@inertiajs/react';
import ProgressBar from '@/components/humetria/progress-bar';
import type { CampaignSending } from '@/types';

/**
 * How the last batch of survey links goes while the queue sends it. The page asks again every few seconds and this
 * goes away once every link of the batch was sent or given up on.
 */
export default function SendingProgress({
    sending,
}: {
    sending: CampaignSending;
}) {
    usePoll(3000, {
        only: ['sending', 'people', 'standings', 'instruments'],
    });

    return (
        <section
            aria-live="polite"
            className="flex flex-col gap-2 rounded-xl border bg-card p-6"
        >
            <p className="font-medium">
                Enviando invitaciones {sending.done} de {sending.total}
            </p>
            <ProgressBar
                value={sending.done}
                max={sending.total}
                label="Invitaciones enviadas"
            />
        </section>
    );
}
