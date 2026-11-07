import { Fingerprint } from 'lucide-react';
import { useState } from 'react';
import DisputeForm from '@/components/attendance/dispute-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { WeekView } from '@/lib/attendance/week';
import { statusText } from '@/lib/attendance/week-text';

const BADGES: Record<
    string,
    'warning' | 'success' | 'destructive' | 'secondary'
> = {
    pending: 'warning',
    confirmed: 'success',
    disputed: 'destructive',
    open: 'secondary',
};

/**
 * Where the week stands and the person's answer: confirm it, or say what is not right. Each answer asks for their
 * fingerprint, face or PIN again; nothing is answered for them.
 */
export default function WeekAnswer({
    week,
    working,
    onConfirm,
    onDispute,
}: {
    week: WeekView;
    working: boolean;
    onConfirm: (url: string) => void;
    onDispute: (url: string, reason: string) => Promise<boolean>;
}) {
    const [disputing, setDisputing] = useState(false);
    const { label, detail } = statusText(week);
    const { confirmUrl, disputeUrl } = week;

    return (
        <section
            aria-labelledby="week-answer-title"
            className="flex flex-col gap-3 card p-5"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2
                    id="week-answer-title"
                    className="text-xl leading-7 font-semibold"
                >
                    Su respuesta
                </h2>
                <Badge variant={BADGES[week.status] ?? 'secondary'}>
                    {label}
                </Badge>
            </div>
            <p role="status" className="text-base">
                {detail}
            </p>
            {week.disputeReason !== null && (
                <blockquote className="border-l-2 pl-3 text-base text-muted-foreground">
                    {week.disputeReason}
                </blockquote>
            )}
            {disputing && disputeUrl !== null ? (
                <DisputeForm
                    working={working}
                    onCancel={() => setDisputing(false)}
                    onSend={(reason) =>
                        void onDispute(disputeUrl, reason).then(
                            (done) => done && setDisputing(false),
                        )
                    }
                />
            ) : (
                <div className="flex flex-col gap-2">
                    {confirmUrl !== null && (
                        <Button
                            className="h-16 w-full text-lg font-semibold"
                            disabled={working}
                            onClick={() => onConfirm(confirmUrl)}
                        >
                            <Fingerprint aria-hidden className="size-6" />
                            Confirmar mi semana
                        </Button>
                    )}
                    {disputeUrl !== null && (
                        <Button
                            variant="outline"
                            size="lg"
                            className="w-full text-base"
                            disabled={working}
                            onClick={() => setDisputing(true)}
                        >
                            No estoy de acuerdo
                        </Button>
                    )}
                </div>
            )}
        </section>
    );
}
