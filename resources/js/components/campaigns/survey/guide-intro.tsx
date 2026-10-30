import { Button } from '@/components/ui/button';
import type { SurveyInstrument } from '@/types';

/**
 * What the person should know before a guide: Guía I carries their name, and why; Guías II and III are anonymous.
 */
export default function GuideIntro({
    instrument,
    onStart,
}: {
    instrument: SurveyInstrument;
    onStart: () => void;
}) {
    return (
        <section className="flex flex-col gap-5">
            <p className="text-sm font-medium tracking-[0.02em] text-muted-foreground uppercase">
                {instrument.shortTitle}
            </p>
            <h1 className="text-3xl leading-[38px] font-semibold tracking-[-0.01em]">
                {instrument.title}
            </h1>
            <div className="rounded-xl bg-accent p-5 text-lg leading-7 text-accent-foreground">
                {instrument.identified ? (
                    <p>
                        Esta parte{' '}
                        <strong className="font-semibold">
                            sí lleva su nombre
                        </strong>
                        : la empresa está obligada a ofrecer atención a quien
                        haya vivido un acontecimiento traumático en el trabajo.
                        Solo la ven las personas autorizadas.
                    </p>
                ) : (
                    <p>
                        Esta parte es{' '}
                        <strong className="font-semibold">anónima</strong>: sus
                        respuestas no llevan su nombre y nadie de su empresa
                        puede ver lo que contesta. Los resultados se presentan
                        solo por grupos.
                    </p>
                )}
            </div>
            <Button size="lg" className="h-14 text-lg" onClick={onStart}>
                Comenzar
            </Button>
        </section>
    );
}
