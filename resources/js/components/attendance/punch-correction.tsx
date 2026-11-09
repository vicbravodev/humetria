import { useState } from 'react';
import CorrectionForm from '@/components/attendance/correction-form';
import PunchFeedback from '@/components/attendance/punch-feedback';
import { Button } from '@/components/ui/button';
import { usePunchCorrection } from '@/hooks/use-punch-correction';
import { clockNow } from '@/lib/attendance/correction';

/** When a mark was refused: the person asks RH to add it, at the time on their clock, and is told it arrived. */
export default function PunchCorrection({
    optionsUrl,
    url,
    timezone,
}: {
    optionsUrl: string;
    url: string;
    /** The company's time zone: the time asked is read on its clock. */
    timezone: string;
}) {
    // The clock is read when the person opens the form: the time of the mark refused.
    const [asking, setAsking] = useState<{ date: string; time: string } | null>(
        null,
    );
    const { working, feedback, ask } = usePunchCorrection({ optionsUrl, url });

    return (
        <>
            {asking !== null ? (
                <CorrectionForm
                    title="Pedir a Recursos Humanos que agregue su marca"
                    markId={null}
                    type={null}
                    date={asking.date}
                    time={asking.time}
                    working={working}
                    onSend={(draft) =>
                        void ask(draft).then((sent) => sent && setAsking(null))
                    }
                    onCancel={() => setAsking(null)}
                />
            ) : (
                <Button
                    type="button"
                    variant="outline"
                    size="lg"
                    className="text-base"
                    onClick={() => setAsking(clockNow(new Date(), timezone))}
                >
                    Pedir corrección a Recursos Humanos
                </Button>
            )}
            <PunchFeedback feedback={feedback} />
        </>
    );
}
