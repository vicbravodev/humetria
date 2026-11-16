import AgreementCard from '@/components/attendance/agreement-card';
import PersonFrame from '@/components/attendance/person-frame';
import PersonHeading from '@/components/humetria/person-heading';
import { Button } from '@/components/ui/button';
import { usePunchAgreement } from '@/hooks/use-punch-agreement';
import type { Agreement } from '@/lib/attendance/punch-agreement';

/**
 * Before the first mark (criterio 5.1), or when its text changed: the agreement on the electronic record, whole, to read
 * and accept with the person's fingerprint, face or PIN. Once accepted, the mark they asked for is tried again.
 */
export default function PunchAgreement({
    company,
    agreement,
    optionsUrl,
    url,
    onAccepted,
    onLater,
}: {
    company: string;
    agreement: Agreement;
    optionsUrl: string;
    url: string;
    onAccepted: () => void;
    onLater: () => void;
}) {
    const accepting = usePunchAgreement({
        optionsUrl,
        url,
        initial: agreement,
        onAccepted,
    });

    return (
        <PersonFrame
            company={company}
            actions={
                <>
                    <Button
                        className="h-14 w-full text-lg font-semibold"
                        disabled={accepting.sending}
                        onClick={() => void accepting.accept()}
                    >
                        {accepting.sending ? 'Confirmando…' : 'Acepto'}
                    </Button>
                    <Button
                        variant="outline"
                        size="lg"
                        className="w-full text-base"
                        disabled={accepting.sending}
                        onClick={onLater}
                    >
                        Ahora no
                    </Button>
                </>
            }
        >
            <div className="flex w-full max-w-md flex-col gap-5">
                <PersonHeading title="Antes de marcar, acepte el acuerdo">
                    {company} le pide aceptar cómo se registra su jornada. Al
                    tocar «Acepto», confirme con su huella, su rostro o su PIN.
                </PersonHeading>
                {accepting.error !== null && (
                    <p
                        role="alert"
                        className="rounded-xl bg-accent px-4 py-3 text-base"
                    >
                        {accepting.error}
                    </p>
                )}
                <AgreementCard
                    version={accepting.agreement.version}
                    body={accepting.agreement.body}
                />
            </div>
        </PersonFrame>
    );
}
