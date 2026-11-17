import { Clock, ShieldCheck } from 'lucide-react';
import IntroDetails from '@/components/complaints-public/intro-details';
import { countOf } from '@/lib/plural';
import type { ExternalAlternative } from '@/types';

/**
 * Before writing (board V4-Quejas-Form): what the channel is for, when it is acknowledged and answered, what it protects and
 * what may still identify the person, the procedure the company wrote, and the federal inspection.
 */
export default function FormIntro({
    procedure,
    acknowledgeWithinDays,
    resolveWithinDays,
    alternative,
}: {
    procedure: string;
    acknowledgeWithinDays: number;
    resolveWithinDays: number;
    alternative: ExternalAlternative;
}) {
    return (
        <div className="flex flex-1 flex-col gap-5">
            <div className="flex flex-col gap-2.5">
                <h1 className="text-[26px] leading-8 font-semibold tracking-[-0.01em]">
                    ¿Algo no está bien en su trabajo?
                </h1>
                <p className="text-muted-foreground">
                    Aquí puede avisar de un mal ambiente de trabajo, de
                    violencia laboral o de un hecho muy grave que le afectó.
                    Puede hacerlo sin dar su nombre.
                </p>
            </div>
            <section
                aria-label="Qué protegemos y qué no"
                className="flex flex-col gap-3 card p-4 text-base"
            >
                <p className="flex items-start gap-3">
                    <Clock aria-hidden className="mt-0.5 size-5 shrink-0" />
                    <span>
                        Le confirmamos que la recibimos en{' '}
                        <strong className="font-semibold">
                            {countOf(acknowledgeWithinDays, 'día', 'días')}
                        </strong>{' '}
                        y le respondemos en{' '}
                        <strong className="font-semibold">
                            {countOf(resolveWithinDays, 'día', 'días')}
                        </strong>
                        .
                    </span>
                </p>
                <p className="flex items-start gap-3">
                    <ShieldCheck
                        aria-hidden
                        className="mt-0.5 size-5 shrink-0"
                    />
                    No guardamos su dirección de internet ni su navegador. La
                    lee solo quien la empresa designó para atender quejas. Si su
                    queja es contra Recursos Humanos o contra la dirección, no
                    llega a ellos.
                </p>
                <IntroDetails procedure={procedure} />
            </section>
            <p className="text-sm text-muted-foreground">
                También puede acudir a la {alternative.name}:{' '}
                {alternative.email} · {alternative.phone}.
            </p>
        </div>
    );
}
