import { CircleCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { describeDate } from '@/lib/calendar-date';

/** After the two questions (board LS-Portal): it was sent, when, and what happens with it; the person may answer again. */
export default function PortalSent({
    sentOn,
    onAgain,
}: {
    sentOn: string;
    onAgain: () => void;
}) {
    return (
        <section className="flex flex-col items-start gap-3">
            <CircleCheck aria-hidden className="size-10 text-primary" />
            <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                Gracias, quedó enviado
            </h1>
            <p>
                Nos contestó el {describeDate(sentOn) ?? sentOn}. La Comisión lo
                tomará en cuenta en su evaluación.
            </p>
            <Button variant="outline" onClick={onAgain}>
                Volver a contestar
            </Button>
        </section>
    );
}
