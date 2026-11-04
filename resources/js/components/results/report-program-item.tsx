import ReportProgramActionLine from '@/components/results/report-program-action';
import type { ReportProgramItem } from '@/types';

/** A row of the Programa in the report: what it attends, its level, the analysis and its actions with the follow-up. */
export default function ReportProgramItemBlock({
    item,
}: {
    item: ReportProgramItem;
}) {
    return (
        <li className="grid break-inside-avoid gap-2">
            <p className="font-semibold">
                {item.label}
                <span className="font-normal text-muted-foreground">
                    {' '}
                    · {item.scope}
                    {item.level === null ? '' : ` · riesgo ${item.level}`}
                </span>
            </p>
            {item.analysis !== null && (
                <p className="whitespace-pre-line">{item.analysis}</p>
            )}
            {item.actions.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Sin acciones registradas.
                </p>
            ) : (
                <ul className="grid gap-2.5">
                    {item.actions.map((action) => (
                        <ReportProgramActionLine
                            key={`${action.title}-${action.dueOn}`}
                            action={action}
                        />
                    ))}
                </ul>
            )}
            {item.followUp !== null && (
                <p className="text-sm">
                    <span className="font-medium">Evaluación posterior:</span>{' '}
                    {item.followUp}
                </p>
            )}
        </li>
    );
}
