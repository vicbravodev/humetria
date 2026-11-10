import { Button } from '@/components/ui/button';
import type { ProblemAction } from '@/lib/attendance/punch-problem';

/** The buttons at the foot of a problem: the first is the main one, the others come as outlines. */
export default function PunchProblemActions({
    actions,
    onAction,
}: {
    actions: ProblemAction[];
    onAction: (kind: ProblemAction['kind']) => void;
}) {
    return (
        <>
            {actions.map(({ kind, label }, index) => (
                <Button
                    key={kind}
                    variant={index === 0 ? 'default' : 'outline'}
                    size="lg"
                    className={
                        index === 0
                            ? 'h-14 w-full text-lg font-semibold'
                            : 'w-full text-base'
                    }
                    onClick={() => onAction(kind)}
                >
                    {label}
                </Button>
            ))}
        </>
    );
}
