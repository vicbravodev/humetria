import { Info } from 'lucide-react';
import EnrollProgress from '@/components/attendance/enroll-progress';
import PersonFrame from '@/components/attendance/person-frame';
import PersonHeading from '@/components/attendance/person-heading';
import Initials from '@/components/humetria/initials';
import { Button } from '@/components/ui/button';

/** Step 2 (board P1): the link is personal; the person confirms it is theirs, so nobody links another person's phone. */
export default function EnrollConfirm({
    company,
    name,
    jobTitle,
    onConfirm,
    onNotYou,
}: {
    company: string;
    name: string;
    jobTitle: string;
    onConfirm: () => void;
    onNotYou: () => void;
}) {
    return (
        <PersonFrame
            company={company}
            actions={
                <>
                    <Button
                        className="h-14 w-full text-lg font-semibold"
                        onClick={onConfirm}
                    >
                        Sí, soy yo
                    </Button>
                    <Button
                        variant="outline"
                        size="lg"
                        className="w-full text-base"
                        onClick={onNotYou}
                    >
                        No soy yo
                    </Button>
                </>
            }
        >
            <div className="flex w-full max-w-md flex-col gap-5">
                <EnrollProgress step="confirm" />
                <PersonHeading title="Confirme que es usted">
                    Este enlace es personal. Verificamos que el teléfono sea el
                    suyo.
                </PersonHeading>
                <section className="flex items-center gap-4 card p-5">
                    <Initials name={name} className="size-12 text-base" />
                    <div className="flex min-w-0 flex-col">
                        <p className="text-xl leading-7 font-semibold">
                            {name}
                        </p>
                        <p className="text-base text-muted-foreground">
                            {jobTitle}
                        </p>
                    </div>
                </section>
                <p className="flex items-start gap-3 rounded-xl bg-accent px-4 py-3.5 text-base">
                    <Info
                        aria-hidden
                        className="mt-0.5 size-5 shrink-0 text-primary"
                    />
                    Si no es {name}, cierre esta página. El enlace no se
                    comparte.
                </p>
            </div>
        </PersonFrame>
    );
}
