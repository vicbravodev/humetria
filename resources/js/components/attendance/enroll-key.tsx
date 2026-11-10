import { Fingerprint } from 'lucide-react';
import EnrollProgress from '@/components/attendance/enroll-progress';
import PersonFrame from '@/components/attendance/person-frame';
import PersonHeading from '@/components/attendance/person-heading';
import ToneTile from '@/components/attendance/tone-tile';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/** Step 3 (board P1): the phone creates the key with the person's fingerprint, face or PIN: one touch, no passwords. */
export default function EnrollKey({
    company,
    working,
    onCreate,
}: {
    company: string;
    working: boolean;
    onCreate: () => void;
}) {
    return (
        <PersonFrame
            company={company}
            actions={
                <>
                    <Button
                        className="h-14 w-full text-lg font-semibold"
                        disabled={working}
                        onClick={onCreate}
                    >
                        {working ? (
                            <Spinner className="size-6" />
                        ) : (
                            <Fingerprint aria-hidden className="size-6" />
                        )}
                        Crear llave
                    </Button>
                    <p className="text-center text-base text-muted-foreground">
                        Tarda unos segundos.
                    </p>
                </>
            }
        >
            <div className="flex w-full max-w-md flex-col gap-5">
                <EnrollProgress step="key" />
                <PersonHeading title="Cree su llave de acceso">
                    Es la huella, el rostro o el PIN que ya usa para desbloquear
                    este teléfono. Nadie más puede marcar por usted.
                </PersonHeading>
                <section className="flex flex-col items-start gap-3 card p-5">
                    <ToneTile icon={Fingerprint} />
                    <p className="text-xl leading-7 font-semibold">
                        Huella o rostro
                    </p>
                    <p className="text-base text-muted-foreground">
                        El teléfono le pedirá confirmar.
                    </p>
                </section>
            </div>
        </PersonFrame>
    );
}
