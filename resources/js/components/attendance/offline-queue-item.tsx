import { useState } from 'react';
import PunchCorrection from '@/components/attendance/punch-correction';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { KeptMark } from '@/hooks/use-offline-queue';
import { markName } from '@/lib/attendance/next-step';
import { clockOf, dayOf } from '@/lib/attendance/offline-time';
import { refusedText, unsavedOnServer } from '@/lib/attendance/offline-text';
import { dayTitle } from '@/lib/attendance/week-text';

/**
 * A mark the server did not accept, kept in the phone: what it was, the hour the person said, why, and the two ways out.
 * «Entendido» drops it, but when the server has no record of it (nothing was saved there) it asks first, since dropping it
 * would lose it. «Pedir corrección» asks RH to add it, which needs the network; once sent, the mark leaves the phone.
 */
export default function OfflineQueueItem({
    mark,
    correction,
    online,
}: {
    mark: KeptMark;
    /** Where a correction is asked; null where it cannot be (the app's shell, with no page of the server). */
    correction: { optionsUrl: string; url: string } | null;
    online: boolean;
}) {
    const [confirming, setConfirming] = useState(false);
    const [asking, setAsking] = useState(false);
    const canAsk = correction !== null && online;

    return (
        <li className="flex flex-col gap-3 card p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-base font-semibold">{markName(mark.type)}</p>
                <Badge variant="destructive">No aceptada</Badge>
            </div>
            <p className="text-base text-muted-foreground">
                Usted indicó {clockOf(mark.declaredAt)} ·{' '}
                {dayTitle(dayOf(mark.declaredAt))}
            </p>
            <p className="text-base">{refusedText(mark.reason)}</p>
            {confirming && (
                <p role="alert" className="text-base font-medium">
                    Recursos Humanos no tiene esta marca. Si la quita, se
                    pierde.
                </p>
            )}
            <div className="flex flex-wrap gap-2.5">
                <Button
                    variant={confirming ? 'destructive' : 'outline'}
                    className="h-12 text-base"
                    onClick={() =>
                        unsavedOnServer(mark.reason) && !confirming
                            ? setConfirming(true)
                            : void mark.dismiss()
                    }
                >
                    {confirming ? 'Quitarla' : 'Entendido'}
                </Button>
                <Button
                    variant="outline"
                    className="h-12 text-base"
                    disabled={!canAsk}
                    aria-describedby={
                        canAsk ? undefined : `${mark.seq}-offline`
                    }
                    onClick={() => setAsking(true)}
                >
                    Pedir corrección
                </Button>
            </div>
            {!canAsk && (
                <p
                    id={`${mark.seq}-offline`}
                    className="text-meta text-muted-foreground"
                >
                    Necesita internet para pedirla
                </p>
            )}
            {correction !== null && (
                <PunchCorrection
                    optionsUrl={correction.optionsUrl}
                    url={correction.url}
                    asking={
                        asking
                            ? {
                                  date: dayOf(mark.declaredAt),
                                  time: clockOf(mark.declaredAt),
                              }
                            : null
                    }
                    onClose={() => setAsking(false)}
                    onSent={() => void mark.dismiss()}
                />
            )}
        </li>
    );
}
