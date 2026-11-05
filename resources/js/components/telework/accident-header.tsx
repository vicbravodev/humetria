import { Badge } from '@/components/ui/badge';
import { describeDate } from '@/lib/calendar-date';
import { accidentBadge, shortDay } from '@/lib/telework/accident';
import type { AccidentNotice } from '@/types';

/** The top of an accident (board V3-Aviso): how long it waits, what it is, and what the person wrote, in their words. */
export default function AccidentHeader({ notice }: { notice: AccidentNotice }) {
    const badge = accidentBadge(notice);

    return (
        <>
            <header className="flex flex-col gap-3">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <Badge variant={badge.tone}>{badge.text}</Badge>
                    <span className="text-sm text-muted-foreground">
                        Accidente o riesgo de trabajo · numeral 6.9
                    </span>
                </div>
                <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em] text-balance">
                    {notice.person} avisó un accidente de trabajo
                </h1>
            </header>
            <blockquote className="flex flex-col gap-1.5 rounded-xl bg-muted px-5 py-4">
                <p className="text-lg whitespace-pre-line">
                    «{notice.description}»
                </p>
                <p className="text-sm text-muted-foreground">
                    Escrito por {notice.person} el{' '}
                    {describeDate(notice.writtenOn) ?? notice.writtenOn} ·
                    ocurrió el {shortDay(notice.occurredOn)}
                </p>
            </blockquote>
        </>
    );
}
