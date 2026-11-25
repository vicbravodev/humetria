import { useState } from 'react';
import OfflineFrame from '@/components/attendance/offline-frame';
import OfflineReadinessRow from '@/components/attendance/offline-readiness-row';
import QrScanner from '@/components/attendance/qr-scanner';
import InstallGuide from '@/components/humetria/install-guide';
import PersonHeading from '@/components/humetria/person-heading';
import { Button } from '@/components/ui/button';
import { FEW_TICKETS } from '@/lib/attendance/offline-text';
import type {
    ReadinessFix,
    ReadinessRow,
} from '@/lib/attendance/offline-readiness';

/** What the title says: ready, or not ready and, when there is not a single ticket, that the app must be opened with internet. */
function titleOf(ready: boolean, rows: ReadinessRow[]): string {
    if (ready) {
        return 'Listo para marcar sin internet';
    }

    return rows.some((row) => row.key === 'tickets' && !row.ok)
        ? 'Aún no está listo: ábrala con internet'
        : 'Aún no está listo';
}

/** Asking for the position is how the browser shows its question; the answer is not used. */
function askLocation(): void {
    if ('geolocation' in navigator) {
        navigator.geolocation.getCurrentPosition(
            () => undefined,
            () => undefined,
        );
    }
}

/**
 * «Listo para marcar sin internet» or «Aún no está listo»: what the phone has and lacks, row by row, with the way to fix each
 * one that can be fixed here. With 1 or 2 tickets left it warns. At the foot, how to install the app. It works with no page of
 * the server (it is also seen in the shell of the installed app).
 */
export default function OfflineReadiness({
    company,
    slug,
    readiness,
    prepareUrl,
    onClose,
}: {
    company: string;
    slug: string;
    readiness: { ready: boolean; fewTickets: boolean; rows: ReadinessRow[] };
    prepareUrl: string | null;
    onClose: () => void;
}) {
    const [scanning, setScanning] = useState(false);
    const fix = (what: ReadinessFix) => {
        if (what === 'camera') {
            setScanning(true);
        } else if (what === 'location') {
            askLocation();
        }
    };

    return (
        <OfflineFrame
            company={company}
            actions={
                <Button
                    variant="outline"
                    className="h-14 w-full text-[17px]"
                    onClick={onClose}
                >
                    Volver
                </Button>
            }
        >
            <div className="flex w-full max-w-md flex-col gap-4">
                <PersonHeading title={titleOf(readiness.ready, readiness.rows)}>
                    Esto es lo que necesita este teléfono para guardar sus
                    marcas cuando no hay señal.
                </PersonHeading>
                {readiness.fewTickets && (
                    <p
                        role="status"
                        className="rounded-xl bg-warning px-4 py-3 text-base text-warning-foreground"
                    >
                        {FEW_TICKETS}
                    </p>
                )}
                <ul className="flex flex-col gap-3">
                    {readiness.rows.map((row) => (
                        <OfflineReadinessRow
                            key={row.key}
                            row={row}
                            prepareUrl={prepareUrl}
                            onFix={fix}
                        />
                    ))}
                </ul>
                <InstallGuide />
            </div>
            {scanning && (
                <QrScanner
                    slug={slug}
                    onCode={() => setScanning(false)}
                    onClose={() => setScanning(false)}
                />
            )}
        </OfflineFrame>
    );
}
