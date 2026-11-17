import { Check } from 'lucide-react';
import SurveyArc from '@/components/engagement/survey/survey-arc';

/**
 * The survey was sent: the same closing as NOM-035 (board «Final propuesto»), the arch filled to the end with its check,
 * centered; the thanks and that the link no longer opens anything. No word on clinical attention: this is not NOM-035.
 */
export default function SurveyDone() {
    return (
        <section
            role="status"
            className="flex w-full max-w-sm flex-1 flex-col items-center justify-center gap-4 pb-[8dvh] text-center"
        >
            <SurveyArc value={1} max={1} label="Terminó">
                <Check
                    aria-hidden="true"
                    className="mb-1.5 size-10 text-primary"
                />
            </SurveyArc>
            <div className="flex flex-col gap-1.5">
                <p className="text-sm font-medium tracking-[0.02em] text-primary uppercase">
                    Encuesta enviada
                </p>
                <h1 className="text-[1.625rem] leading-[2.125rem] font-semibold tracking-[-0.01em] text-balance">
                    Gracias. Sus respuestas ya se enviaron.
                </h1>
            </div>
            <p className="text-base text-muted-foreground">
                Ya puede cerrar esta página. El enlace dejó de funcionar para
                que nadie más lo use.
            </p>
        </section>
    );
}
