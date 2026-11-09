import PersonFrame from '@/components/attendance/person-frame';
import PersonProblem from '@/components/attendance/person-problem';
import PunchProblemActions from '@/components/attendance/punch-problem-actions';
import { useCopyMessage } from '@/hooks/use-copy-message';
import type { Problem, ProblemAction } from '@/lib/attendance/punch-problem';
import { hrLinkMessage } from '@/lib/attendance/punch-problem';

/** Linking did not end well (board P3): what happened, what to do, and the way out at the foot: try again, ask RH for a link. */
export default function EnrollProblem({
    company,
    problem,
    onRetry,
}: {
    company: string;
    problem: Problem;
    onRetry: () => void;
}) {
    const { copied, copy } = useCopyMessage();
    const run = (kind: ProblemAction['kind']) => {
        if (kind === 'retry') {
            onRetry();
        } else if (kind === 'copy-hr') {
            copy(hrLinkMessage(company));
        } else if (kind === 'reload') {
            window.location.reload();
        }
    };

    return (
        <PersonFrame
            company={company}
            actions={
                <PunchProblemActions actions={problem.actions} onAction={run} />
            }
        >
            <div className="flex w-full max-w-md flex-col gap-6">
                <PersonProblem problem={problem} />
                {copied && (
                    <p
                        role="status"
                        className="rounded-xl bg-accent px-4 py-3 text-base"
                    >
                        Mensaje copiado. Péguelo en un mensaje a Recursos
                        Humanos.
                    </p>
                )}
            </div>
        </PersonFrame>
    );
}
