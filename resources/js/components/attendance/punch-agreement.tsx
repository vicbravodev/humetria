import AgreementCard from '@/components/attendance/agreement-card';
import PersonFrame from '@/components/attendance/person-frame';
import { Button } from '@/components/ui/button';
import { markName } from '@/lib/attendance/next-step';
import type { MarkType } from '@/lib/attendance/next-step';
import type { Agreement } from '@/lib/attendance/punch-agreement';

/**
 * Before the first mark (criterio 5.1), or when its text changed (board «Asistencia: acuerdo de registro»): the version in a
 * line of context, the agreement whole to read, and at the foot two actions of the same size and weight. «Acepto y marcar
 * entrada» accepts it and marks with one fingerprint, face or PIN (hallazgo 27); the step is the one that proceeds.
 */
export default function PunchAgreement({
    company,
    agreement,
    step,
    onAccept,
    onLater,
}: {
    company: string;
    agreement: Agreement;
    /** The step marked with the acceptance. */
    step: MarkType;
    onAccept: () => void;
    onLater: () => void;
}) {
    const accept = `Acepto y marcar ${markName(step).toLowerCase()}`;

    return (
        <PersonFrame
            company={company}
            actions={
                <>
                    <Button
                        className="h-14 w-full text-[17px] font-semibold"
                        onClick={onAccept}
                    >
                        {accept}
                    </Button>
                    <Button
                        variant="outline"
                        className="h-14 w-full text-[17px]"
                        onClick={onLater}
                    >
                        Ahora no
                    </Button>
                </>
            }
        >
            <div className="flex w-full max-w-md flex-col gap-5">
                <div className="flex flex-col gap-2">
                    <p className="text-caption font-medium tracking-wide text-primary uppercase">
                        Acuerdo de registro · versión {agreement.version}
                    </p>
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em] text-balance">
                        Antes de marcar, acepte el acuerdo
                    </h1>
                    <p className="text-muted-foreground">
                        {company} le pide aceptar cómo se registra su jornada.
                        Al tocar «{accept}», confirme con su huella, su rostro o
                        su PIN.
                    </p>
                </div>
                <AgreementCard body={agreement.body} />
            </div>
        </PersonFrame>
    );
}
