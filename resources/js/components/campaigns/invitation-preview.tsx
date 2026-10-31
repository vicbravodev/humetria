import Initials from '@/components/humetria/initials';
import { describeDate } from '@/lib/calendar-date';

/**
 * The email each person gets, drawn on a phone (pantalla Eval-Borrador): what it says, in "usted" as the survey, with
 * the last day picked. Its words follow the SurveyInvitation notification.
 */
export default function InvitationPreview({
    company,
    lastDay,
}: {
    company: string;
    /** Y-m-d. */
    lastDay: string;
}) {
    return (
        <aside
            className="flex flex-col items-center gap-3.5"
            aria-label="Así le llega a cada persona"
        >
            <span className="text-sm text-muted-foreground">
                Así le llega a cada persona
            </span>
            <div className="w-full max-w-80 rounded-[36px] bg-foreground p-3 shadow-raised">
                <div className="flex min-h-[520px] flex-col overflow-hidden rounded-[26px] bg-muted">
                    <div className="flex items-center gap-2.5 border-b bg-card px-4 pt-4 pb-3">
                        <Initials name={company} kind="company" />
                        <span className="flex min-w-0 flex-col">
                            <span className="truncate text-sm font-semibold">
                                {company}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                Correo
                            </span>
                        </span>
                    </div>
                    <div className="p-4">
                        <div className="flex max-w-[250px] flex-col gap-2 rounded-[4px_14px_14px_14px] border bg-card px-3.5 py-3 text-sm">
                            <p>
                                Hola. {company} le invita a contestar la
                                evaluación de factores de riesgo psicosocial en
                                el trabajo (NOM-035).
                            </p>
                            <p>
                                Sus respuestas sobre su trabajo son{' '}
                                <strong className="font-semibold">
                                    anónimas
                                </strong>
                                : nadie de su empresa puede ver lo que contesta.
                            </p>
                            <p>
                                Puede contestar hasta el{' '}
                                {describeDate(lastDay) ?? '…'}.
                            </p>
                            <span className="font-medium text-primary">
                                Contestar evaluación
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    );
}
