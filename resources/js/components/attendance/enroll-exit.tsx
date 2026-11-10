import { Clock, UserX } from 'lucide-react';
import PersonFrame from '@/components/attendance/person-frame';
import ToneTile from '@/components/attendance/tone-tile';

/** The person left the way without linking (board P1): because the link is not theirs, or because it is not the time. */
export default function EnrollExit({
    company,
    reason,
    linkHours,
}: {
    company: string;
    reason: 'not-you' | 'later';
    linkHours: number;
}) {
    const notYou = reason === 'not-you';

    return (
        <PersonFrame company={company}>
            <section
                role="status"
                className="flex w-full max-w-md flex-col items-start gap-3 pt-2"
            >
                <ToneTile
                    icon={notYou ? UserX : Clock}
                    tone={notYou ? 'warning' : 'accent'}
                    large
                />
                <h1 className="text-2xl leading-8 font-semibold text-balance">
                    {notYou ? 'No se vinculó nada' : 'Puede vincularlo después'}
                </h1>
                <p className="text-muted-foreground">
                    {notYou
                        ? 'Cierre esta página y avise a Recursos Humanos que este enlace le llegó a usted por error. No se guardó nada de este teléfono.'
                        : `Este enlace sirve una vez y dura ${linkHours} horas. Cuando quiera, ábralo otra vez para continuar.`}
                </p>
            </section>
        </PersonFrame>
    );
}
