import { Clock } from 'lucide-react';
import { countOf } from '@/lib/plural';

/** The procedure the company wrote and how soon it answers, for the person to read before deciding. */
export default function HowItWorks({
    procedure,
    acknowledgeWithinDays,
    resolveWithinDays,
}: {
    procedure: string;
    acknowledgeWithinDays: number;
    resolveWithinDays: number;
}) {
    return (
        <section className="flex flex-col gap-3 card p-5">
            <h2 className="text-lg font-semibold">Cómo se atiende</h2>
            <p className="whitespace-pre-line">{procedure}</p>
            <p className="flex items-start gap-2 text-base text-muted-foreground">
                <Clock aria-hidden className="mt-1 size-4 shrink-0" />
                <span>
                    La empresa se propone acusar recibo en{' '}
                    {countOf(acknowledgeWithinDays, 'día', 'días')} y dar una
                    respuesta en {countOf(resolveWithinDays, 'día', 'días')}.
                    Son plazos que la empresa se fija, no de la norma.
                </span>
            </p>
        </section>
    );
}
