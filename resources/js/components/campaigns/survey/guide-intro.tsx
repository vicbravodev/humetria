import { ArrowLeft, ArrowRight, IdCard, Lock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { SurveyInstrument } from '@/types';

/**
 * What the person should know before a guide: Guía I carries their name, and why; Guías II and III are anonymous.
 */
export default function GuideIntro({
    instrument,
    onBack,
    onStart,
}: {
    instrument: SurveyInstrument;
    onBack: () => void;
    onStart: () => void;
}) {
    const Icon = instrument.identified ? IdCard : Lock;

    return (
        <section className="flex w-full max-w-2xl flex-col gap-[22px]">
            <p className="text-base font-medium text-muted-foreground">
                {instrument.shortTitle}
            </p>
            <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                {instrument.title}
            </h1>
            <div
                className={cn(
                    'flex items-start gap-3 rounded-xl p-4 text-[17px] leading-[26px]',
                    instrument.identified
                        ? 'bg-warning text-warning-foreground'
                        : 'bg-accent text-accent-foreground',
                )}
            >
                <Icon
                    aria-hidden="true"
                    className={cn(
                        'mt-0.5 size-5 shrink-0',
                        !instrument.identified && 'text-primary',
                    )}
                />
                {instrument.identified ? (
                    <p>
                        Esta guía{' '}
                        <strong className="font-semibold">
                            sí lleva su nombre
                        </strong>
                        : la empresa está obligada a ofrecer atención a quien
                        haya vivido un acontecimiento traumático en el trabajo.
                        Solo la ven las personas autorizadas.
                    </p>
                ) : (
                    <p>
                        Esta guía es{' '}
                        <strong className="font-semibold">anónima</strong>: sus
                        respuestas no llevan su nombre y nadie de su empresa
                        puede ver lo que contesta. Los resultados se presentan
                        solo por grupos.
                    </p>
                )}
            </div>
            <div className="flex items-center justify-between gap-3">
                <Button
                    variant="ghost"
                    size="lg"
                    className="px-3 text-[17px]"
                    onClick={onBack}
                >
                    <ArrowLeft aria-hidden="true" />
                    Anterior
                </Button>
                <Button
                    size="lg"
                    className="min-w-40 rounded-[10px] text-[17px]"
                    onClick={onStart}
                >
                    Empezar
                    <ArrowRight aria-hidden="true" />
                </Button>
            </div>
        </section>
    );
}
