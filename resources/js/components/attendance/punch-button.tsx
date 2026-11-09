import { Fingerprint } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { MarkType, Step } from '@/lib/attendance/next-step';

/**
 * The big button of the next step, and the other steps that may follow, smaller (board P2). Each asks the device for the
 * person's fingerprint, face or PIN; the page never advances on its own.
 */
export default function PunchButton({
    primary,
    others,
    onMark,
}: {
    primary: Step | null;
    others: Step[];
    onMark: (type: MarkType) => void;
}) {
    return (
        <>
            {primary !== null && (
                <Button
                    className="h-14 w-full text-lg font-semibold"
                    onClick={() => onMark(primary.type)}
                >
                    <Fingerprint aria-hidden className="size-6" />
                    {primary.label}
                </Button>
            )}
            {others.map((step) => (
                <Button
                    key={step.type}
                    variant="outline"
                    size="lg"
                    className="w-full text-base"
                    onClick={() => onMark(step.type)}
                >
                    {step.label}
                </Button>
            ))}
        </>
    );
}
