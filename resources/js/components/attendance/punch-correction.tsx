import CorrectionSheet from '@/components/attendance/correction-sheet';
import PunchFeedback from '@/components/attendance/punch-feedback';
import { usePunchCorrection } from '@/hooks/use-punch-correction';

/**
 * When a mark was refused: the person asks RH to add it in a sheet from the bottom, at the time of the center's clock (read
 * when they open it: the time of the mark refused), and is told it arrived. Open while `asking` has the date and time.
 */
export default function PunchCorrection({
    optionsUrl,
    url,
    asking,
    onClose,
    onSent,
}: {
    optionsUrl: string;
    url: string;
    asking: { date: string; time: string } | null;
    onClose: () => void;
    /** The request reached RH (before the sheet closes): what the caller does once it was asked. */
    onSent?: () => void;
}) {
    const { working, feedback, ask } = usePunchCorrection({ optionsUrl, url });

    return (
        <>
            <CorrectionSheet
                ask={
                    asking === null
                        ? null
                        : {
                              title: 'Pedir a Recursos Humanos que agregue su marca',
                              markId: null,
                              type: null,
                              ...asking,
                          }
                }
                working={working}
                onSend={(draft) =>
                    void ask(draft).then((sent) => {
                        if (sent) {
                            onSent?.();
                            onClose();
                        }
                    })
                }
                onClose={onClose}
            />
            <PunchFeedback feedback={feedback} />
        </>
    );
}
