import ReportProgramItemBlock from '@/components/results/report-program-item';
import { interventionText } from '@/lib/report';
import type { ReportIntervention } from '@/types';

/**
 * The acciones de intervención of incise g (num. 7.7 g): the Programa as of the day the report is read, or why there is
 * none. It names the areas the results show and who answers, never a file or a note of a step.
 */
export default function ReportProgram({
    intervention,
}: {
    intervention: ReportIntervention;
}) {
    const { program } = intervention;

    if (program === null) {
        return (
            <p className="text-muted-foreground">
                {interventionText(intervention.state)}
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            <p className="text-sm text-muted-foreground">
                Programa de intervención · Estado al {program.asOf}:{' '}
                {program.state.toLowerCase()} ({program.meter.done} de{' '}
                {program.meter.total} acciones hechas)
                {program.responsible === null
                    ? ''
                    : ` · Responsable: ${program.responsible}`}
            </p>
            <ul className="grid gap-5">
                {program.items.map((item) => (
                    <ReportProgramItemBlock key={item.label} item={item} />
                ))}
            </ul>
        </div>
    );
}
