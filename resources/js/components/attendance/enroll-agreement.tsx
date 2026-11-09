import { Check } from 'lucide-react';
import { useState } from 'react';
import AgreementCard from '@/components/attendance/agreement-card';
import EnrollProgress from '@/components/attendance/enroll-progress';
import PersonFrame from '@/components/attendance/person-frame';
import PersonHeading from '@/components/attendance/person-heading';
import { Button } from '@/components/ui/button';
import { AGREEMENT_POINTS } from '@/lib/attendance/enroll';

/**
 * Step 1 of linking the phone (board P1): what the company asks the person to accept, in three lines, with the full text one
 * tap away. «Acepto y continúo» is the acceptance; «Ahora no» leaves it for later.
 */
export default function EnrollAgreement({
    company,
    agreement,
    onAccept,
    onLater,
}: {
    company: string;
    agreement: { version: number; body: string };
    onAccept: () => void;
    onLater: () => void;
}) {
    const [reading, setReading] = useState(false);

    return (
        <PersonFrame
            company={company}
            actions={
                <>
                    <Button
                        className="h-14 w-full text-lg font-semibold"
                        onClick={onAccept}
                    >
                        Acepto y continúo
                    </Button>
                    <Button
                        variant="outline"
                        size="lg"
                        className="w-full text-base"
                        onClick={onLater}
                    >
                        Ahora no
                    </Button>
                </>
            }
        >
            <div className="flex w-full max-w-md flex-col gap-5">
                <EnrollProgress step="agreement" />
                <PersonHeading title="Acuerdo de uso del teléfono">
                    Antes de marcar desde su teléfono, {company} le pide aceptar
                    esto:
                </PersonHeading>
                <ul className="flex flex-col gap-4 card p-5">
                    {AGREEMENT_POINTS.map((point) => (
                        <li key={point} className="flex items-start gap-3">
                            <span
                                aria-hidden
                                className="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-accent text-primary"
                            >
                                <Check className="size-4" />
                            </span>
                            {point}
                        </li>
                    ))}
                </ul>
                <Button
                    variant="link"
                    className="self-start px-0 text-base"
                    aria-expanded={reading}
                    onClick={() => setReading(!reading)}
                >
                    Leer el acuerdo completo
                </Button>
                {reading && (
                    <AgreementCard
                        version={agreement.version}
                        body={agreement.body}
                    />
                )}
            </div>
        </PersonFrame>
    );
}
