import PersonFrame from '@/components/attendance/person-frame';
import PersonProblem from '@/components/attendance/person-problem';
import PunchCorrection from '@/components/attendance/punch-correction';
import PunchProblemActions from '@/components/attendance/punch-problem-actions';
import { useProblemActions } from '@/hooks/use-problem-actions';
import type { Problem } from '@/lib/attendance/punch-problem';

/**
 * A mark that was not taken (board P3): what happened in a title, what to do in a line, and the way out in the buttons at
 * the foot (try again, scan again, ask RH for a correction in a sheet, how to allow the location, ask RH for the link).
 */
export default function PunchProblemView({
    company,
    problem,
    optionsUrl,
    correctionUrl,
    timezone,
    now,
    onRetry,
    onDismiss,
}: {
    company: string;
    problem: Problem;
    optionsUrl: string;
    correctionUrl: string;
    timezone: string;
    /** The server's time now. */
    now: number;
    onRetry: () => void;
    onDismiss: () => void;
}) {
    const { asking, howto, copied, run, close } = useProblemActions({
        company,
        timezone,
        now,
        onRetry,
        onDismiss,
    });

    return (
        <PersonFrame
            company={company}
            actions={
                <PunchProblemActions actions={problem.actions} onAction={run} />
            }
        >
            <div className="flex w-full max-w-md flex-col gap-6">
                <PersonProblem problem={problem} />
                {howto !== null && (
                    <p className="card p-5 text-base">{howto}</p>
                )}
                {copied && (
                    <p
                        role="status"
                        className="rounded-xl bg-accent px-4 py-3 text-base"
                    >
                        Mensaje copiado. Péguelo en un mensaje a Recursos
                        Humanos.
                    </p>
                )}
                <PunchCorrection
                    optionsUrl={optionsUrl}
                    url={correctionUrl}
                    asking={asking}
                    onClose={close}
                />
            </div>
        </PersonFrame>
    );
}
