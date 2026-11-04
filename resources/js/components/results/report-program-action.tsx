import type { ReportProgramAction } from '@/types';

/** One action of the Programa as the report prints it: what, at which level, for whom, when, who answers and its state. */
export default function ReportProgramActionLine({
    action,
}: {
    action: ReportProgramAction;
}) {
    const dates =
        action.startsOn === null
            ? `hasta el ${action.dueOn}`
            : `del ${action.startsOn} al ${action.dueOn}`;

    return (
        <li className="grid break-inside-avoid gap-0.5 border-l-2 pl-3">
            <p className="font-medium">
                {action.title}
                <span className="font-normal text-muted-foreground">
                    {' '}
                    · {action.status}
                    {action.overdue ? ' (vencida)' : ''}
                </span>
            </p>
            <p className="text-sm text-muted-foreground">
                {action.kind} · {action.level}
                {action.professional === null
                    ? ''
                    : ` · ${action.professional}`}
            </p>
            <p className="text-sm text-muted-foreground">
                Para: {action.subjects} · {dates} · Responsable:{' '}
                {action.responsible}
            </p>
        </li>
    );
}
