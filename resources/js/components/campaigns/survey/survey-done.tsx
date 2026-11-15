import ArcMeter from '@/components/humetria/arc-meter';
import type { CampaignKind } from '@/types';

/** Every guide sent: the full arch, thanks, and that the link no longer opens anything. */
export default function SurveyDone({ kind }: { kind: CampaignKind | null }) {
    return (
        <section className="flex w-full max-w-2xl flex-col items-center gap-6 pt-10 text-center">
            <ArcMeter value={1} max={1} label="100 %" size="md" />
            <div className="flex flex-col gap-2.5">
                <h1 className="text-[1.625rem] leading-[2.125rem] font-semibold tracking-[-0.01em]">
                    Gracias, terminó
                </h1>
                <p>
                    Ya contestó todo lo que le correspondía y sus respuestas se
                    enviaron.
                </p>
            </div>
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
