import { Clock, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { countOf } from '@/lib/plural';
import { show } from '@/routes/complaints/follow-up';
import type { ExternalAlternative } from '@/types';

/**
 * Before writing (board V4-Quejas-Form): what the channel is for, when it is acknowledged and answered, what it protects and
 * what may still identify the person, the procedure the company wrote, and the two ways in — or the federal inspection.
 */
export default function FormIntro({
    code,
    procedure,
    acknowledgeWithinDays,
    resolveWithinDays,
    alternative,
    onStart,
}: {
    code: string;
    procedure: string;
    acknowledgeWithinDays: number;
    resolveWithinDays: number;
    alternative: ExternalAlternative;
    onStart: () => void;
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
                    lee solo quien la empresa designó, nunca la persona de la
                    que se queja.
                </p>
                <details className="group">
                    <summary className="flex min-h-11 cursor-pointer items-center font-medium text-primary">
                        Qué puede identificarle aunque no dé su nombre
                    </summary>
                    <ul className="mt-1 list-disc space-y-1 pl-5 text-muted-foreground">
                        <li>
                            Detalles que solo usted conoce o su forma de
                            escribir.
                        </li>
                        <li>Si el centro o el grupo es pequeño.</li>
                        <li>
                            La red de la empresa. Si le preocupa, use los datos
                            de su celular.
                        </li>
                    </ul>
                </details>
                <details className="group">
                    <summary className="flex min-h-11 cursor-pointer items-center font-medium text-primary">
                        Cómo se atiende su queja
                    </summary>
                    <p className="mt-1 whitespace-pre-line text-muted-foreground">
                        {procedure}
                    </p>
                </details>
            </section>
            <div className="mt-auto flex flex-col gap-2 pt-2">
                <Button className="w-full" onClick={onStart}>
                    Presentar una queja
                </Button>
                <Button variant="outline" className="w-full" asChild>
                    <a href={show.url({ code })}>
                        Ya presenté una: ver cómo va
                    </a>
                </Button>
            </div>
            <p className="text-sm text-muted-foreground">
                También puede acudir a la {alternative.name}:{' '}
                {alternative.email} · {alternative.phone}.
            </p>
        </div>
    );
}
