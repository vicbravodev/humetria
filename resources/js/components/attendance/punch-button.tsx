import { Fingerprint } from 'lucide-react';
import PunchEveryStep from '@/components/attendance/punch-every-step';
import { Button } from '@/components/ui/button';
import { nextSteps } from '@/lib/attendance/next-step';
import type { MarkType } from '@/lib/attendance/next-step';
import type { Signer } from '@/lib/attendance/punch';
import { cn } from '@/lib/utils';

/**
 * The steps at the foot of Marcar, compact so the content is seen (hallazgo 16). Nobody known: the four in two rows. A
 * person known: the big button of their next step and the others smaller, two per row. Each asks for their fingerprint,
 * face or PIN; the page never advances on its own.
 */
export default function PunchButton({
    person,
    onMark,
}: {
    person: Signer | null;
    onMark: (type: MarkType) => void;
}) {
    if (person === null) {
        return <PunchEveryStep onMark={onMark} />;
    }

    const { primary, others } = nextSteps(person.next);

    return (
        <>
            {primary !== null && (
                <Button
                    className="h-14 w-full text-[17px] font-semibold"
                    onClick={() => onMark(primary.type)}
                >
                    <Fingerprint aria-hidden className="size-5" />
                    {primary.label}
                </Button>
            )}
            {others.length > 0 && (
                <div
                    className={cn(
                        'grid gap-2.5',
                        others.length > 1 && 'grid-cols-2',
                    )}
                >
                    {others.map((step) => (
                        <Button
                            key={step.type}
                            variant="outline"
                            className="h-12 text-base"
                            onClick={() => onMark(step.type)}
                        >
                            {step.label}
                        </Button>
                    ))}
                </div>
            )}
        </>
    );
}
