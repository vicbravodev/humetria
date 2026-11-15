import { describeDate } from '@/lib/calendar-date';
import { CircleCheck } from 'lucide-react';
import PortalAcknowledgment from '@/components/seating/portal-acknowledgment';
import type { PortalAcknowledgment as Pending } from '@/types';

/** The confirmation of the person's assessment: the one that waits for it, or the day they gave it. */
export default function PortalConfirmation({
    token,
    acknowledgment,
    acknowledgedOn,
}: {
    token: string;
    acknowledgment: Pending | null;
    acknowledgedOn: string | null;
}) {
    return (
        <>
            {acknowledgment !== null && (
                <PortalAcknowledgment token={token} pending={acknowledgment} />
            )}
            {acknowledgedOn !== null && (
                <p className="flex items-center gap-2 rounded-xl bg-accent px-4 py-3 text-base text-accent-foreground">
                    <CircleCheck aria-hidden className="size-5 shrink-0" />
                    Usted confirmó el{' '}
                    {describeDate(acknowledgedOn) ?? acknowledgedOn} que le
                    informaron del resultado de su evaluación.
                </p>
            )}
        </>
    );
}
