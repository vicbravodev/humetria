import { describeDate } from '@/lib/calendar-date';
import type { PortalNoticeAnswer as Answer } from '@/types';

/** What the company did about the person's accident, as they recorded it (board V3-Aviso), in usted. */
export default function PortalNoticeAnswer({ answer }: { answer: Answer }) {
    return (
        <div className="grid gap-1.5 rounded-lg bg-muted px-3.5 py-3 text-base">
            <p className="font-medium">
                Respuesta del{' '}
                {describeDate(answer.answeredOn) ?? answer.answeredOn}
            </p>
            <ul className="grid list-disc gap-0.5 pl-5">
                {answer.actions.map((action) => (
                    <li key={action}>{action}</li>
                ))}
            </ul>
            {answer.note !== null && (
                <p className="whitespace-pre-line">{answer.note}</p>
            )}
        </div>
    );
}
