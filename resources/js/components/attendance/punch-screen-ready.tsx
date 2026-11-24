import PunchOfflineNotes from '@/components/attendance/punch-offline-notes';
import PunchReady from '@/components/attendance/punch-ready';
import type { useOfflineAwarePunch } from '@/hooks/use-offline-aware-punch';
import type { PunchMode } from '@/lib/attendance/punch';

/**
 * The screen before marking, and between marks, with what is said of the marks made without a connection: the queue, the ones
 * the server did not accept and the way to ask whether this phone is ready.
 */
export default function PunchScreenReady({
    company,
    punch,
    page,
    now,
}: {
    company: string;
    punch: ReturnType<typeof useOfflineAwarePunch>;
    page: {
        mode: PunchMode;
        scannedAt: string | null;
        weekUrl: string;
        enterUrl: string;
        correctionOptionsUrl: string;
        correctionUrl: string;
        timezone: string;
    };
    /** The server's time now. */
    now: number;
}) {
    return (
        <PunchReady
            company={company}
            person={punch.person}
            marks={punch.marks}
            feedback={punch.feedback}
            mode={page.mode}
            scannedAt={page.scannedAt}
            weekUrl={page.weekUrl}
            enterUrl={page.enterUrl}
            timezone={page.timezone}
            now={now}
            readInApp={punch.readInApp}
            notes={
                <PunchOfflineNotes
                    flow={punch.offline}
                    correction={{
                        optionsUrl: page.correctionOptionsUrl,
                        url: page.correctionUrl,
                    }}
                />
            }
            onMark={punch.mark}
            onCode={punch.readCode}
            onForget={punch.forget}
        />
    );
}
