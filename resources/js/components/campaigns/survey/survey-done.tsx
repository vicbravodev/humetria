import { Check } from 'lucide-react';
import SurveyArc from '@/components/campaigns/survey/survey-arc';
import type { CampaignKind } from '@/types';

/**
 * Every guide sent (board «Final propuesto», the same closing as the pulse): the arch fills to the end with its check,
 * thanks, and that the link no longer opens anything, in the middle of the screen.
 */
export default function SurveyDone({ kind }: { kind: CampaignKind | null }) {
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
            <h1 className="text-[1.625rem] leading-[2.125rem] font-semibold tracking-[-0.01em]">
                Gracias, terminó
            </h1>
            <p>
                Ya contestó todo lo que le correspondía y sus respuestas se
                enviaron.
            </p>
            {kind?.startsWith('nom035') === true && (
                // The same words for everyone: the screen never says whether anyone needs a clinical assessment.
                <p className="rounded-xl bg-accent px-4 py-3.5 text-[1.0625rem] leading-[1.625rem] text-accent-foreground">
                    Si algo de lo que contestó le sigue afectando, puede pedir
                    atención a Recursos Humanos o acudir a su clínica del IMSS.
                    Si sus respuestas indican que la necesita, Recursos Humanos
                    le buscará.
                </p>
            )}
            <p className="text-base text-muted-foreground">
                Ya puede cerrar esta página. El enlace dejó de funcionar para
                que nadie más lo use.
            </p>
        </section>
    );
}
