import { ShieldQuestion, UserRoundCheck } from 'lucide-react';
import type { CaseDetail } from '@/types';

/** Who presented it, if they chose to say; an anonymous complaint says so, and there is nothing to ask for. */
export default function CaseIdentity({ detail }: { detail: CaseDetail }) {
    if (detail.identity === 'anonymous') {
        return (
            <p className="flex items-start gap-3 card px-5 py-4 text-meta text-muted-foreground">
                <ShieldQuestion
                    aria-hidden
                    className="mt-0.5 size-5 shrink-0"
                />
                Anónima: no hay nombre ni forma de contactar a la persona. Solo
                puedes escribirle por el seguimiento de la referencia.
            </p>
        );
    }

    return (
        <section className="flex items-start gap-3 card p-5">
            <UserRoundCheck
                aria-hidden
                className="mt-0.5 size-5 shrink-0 text-primary"
            />
            <div>
                <h2 className="font-semibold">{detail.reporterName}</h2>
                <p className="text-muted-foreground">
                    {detail.reporterContact ?? 'No dejó una forma de contacto.'}
                </p>
                <p className="mt-1 text-caption text-muted-foreground">
                    Lo eligió así: solo lo ven quienes tienen asignado el caso.
                </p>
            </div>
        </section>
    );
}
