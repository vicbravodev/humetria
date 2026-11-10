import CorrectionForm from '@/components/attendance/correction-form';
import PunchFeedback from '@/components/attendance/punch-feedback';
import { usePunchCorrection } from '@/hooks/use-punch-correction';

/**
 * When a mark was refused: the person asks RH to add it, at the time on their clock (read when they open the form: the time
 * of the mark refused), and is told it arrived. Open while `asking` has the date and time to propose.
 */
export default function PunchCorrection({
    optionsUrl,
    url,
    asking,
    onClose,
}: {
    optionsUrl: string;
    url: string;
    asking: { date: string; time: string } | null;
    onClose: () => void;
}) {
    const { working, feedback, ask } = usePunchCorrection({ optionsUrl, url });

    return (
        <>
            {asking !== null && (
                <CorrectionForm
                    title="Pedir a Recursos Humanos que agregue su marca"
                    markId={null}
                    type={null}
                    date={asking.date}
                    time={asking.time}
                    working={working}
                    onSend={(draft) =>
                        void ask(draft).then((sent) => sent && onClose())
                    }
                    onCancel={onClose}
                />
            )}
            <PunchFeedback feedback={feedback} />
        </>
    );
}
