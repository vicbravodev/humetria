import { Fingerprint } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { MarkType, Step } from '@/lib/attendance/next-step';

/**
 * The big button of the next step, and the other steps that may follow, smaller. Each asks the device for the person's
 * fingerprint, face or PIN; the page never advances on its own.
 */
export default function PunchButton({
    primary,
    others,
    working,
    onMark,
}: {
    primary: Step | null;
    others: Step[];
    working: boolean;
    onMark: (type: MarkType) => void;
}) {
    return (
        <section aria-label="Marcar" className="flex flex-col gap-3">
            {primary !== null && (
                <Button
                    className="h-20 w-full text-xl font-semibold"
                    disabled={working}
                    onClick={() => onMark(primary.type)}
                >
                    {working ? (
                        <Spinner className="size-6" />
                    ) : (
                        <Fingerprint aria-hidden className="size-6" />
                    )}
                    {primary.label}
                </Button>
            )}
            {others.map((step) => (
                <Button
                    key={step.type}
                    variant="outline"
                    size="lg"
                    className="w-full text-base"
                    disabled={working}
                    onClick={() => onMark(step.type)}
                >
                    {step.label}
                </Button>
            ))}
        </section>
    );
}
