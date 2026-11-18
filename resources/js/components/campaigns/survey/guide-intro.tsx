import { ArrowLeft, ArrowRight, IdCard, Lock } from 'lucide-react';
import SurveyActions from '@/components/campaigns/survey/survey-actions';
import { Button } from '@/components/ui/button';
import { groupNoticeText } from '@/lib/group-minimum';
import type { IntroCopy } from '@/lib/survey';
import { cn } from '@/lib/utils';
import type { SurveyInstrument } from '@/types';

/**
 * What the person should know before a guide: Guía I carries their name, and why; Guías II and III are anonymous, with the
 * minimum of people per group the evaluation froze (with none, the warning instead of a promise).
 */
export default function GuideIntro({
    instrument,
    noun,
    minGroup,
    onBack,
    onStart,
}: {
    instrument: SurveyInstrument;
    noun: IntroCopy['noun'];
    minGroup: number | null;
    onBack: () => void;
    onStart: () => void;
}) {
    const Icon = instrument.identified ? IdCard : Lock;

    return (
        <section className="flex w-full max-w-2xl flex-col gap-[22px]">
            <p className="text-base font-medium text-muted-foreground">
                {instrument.shortTitle}
            </p>
            <h1 className="text-[1.625rem] leading-[2.125rem] font-semibold tracking-[-0.01em]">
                {instrument.title}
            </h1>
            <div
                className={cn(
                    'flex items-start gap-3 rounded-xl p-4 text-[1.0625rem] leading-[1.625rem]',
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
                        Esta {noun}{' '}
                        <strong className="font-semibold">
                            sí lleva su nombre
                        </strong>
                        : la empresa está obligada a ofrecer atención a quien
                        haya vivido un acontecimiento traumático en el trabajo.
                        Solo la ven las personas autorizadas.
                    </p>
                ) : (
                    <p>
                        Esta {noun} es{' '}
                        <strong className="font-semibold">anónima</strong>: sus
                        respuestas no llevan su nombre y nadie de su empresa
                        puede ver lo que contesta.
                        {minGroup !== null && ` ${groupNoticeText(minGroup)}`}
                    </p>
                )}
            </div>
            <p className="text-base text-muted-foreground">
                Sus respuestas se guardan en este equipo hasta que las envíe. Si
                el equipo es compartido, termine en una sola sesión.
            </p>
            <SurveyActions>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button
                        variant="ghost"
                        size="lg"
                        className="px-3 text-[1.0625rem]"
                        onClick={onBack}
                    >
                        <ArrowLeft aria-hidden="true" />
                        Anterior
                    </Button>
                    <Button
                        size="lg"
                        className="h-[3.25rem] max-w-full min-w-40 flex-1 rounded-[10px] text-[1.0625rem] md:h-10 md:flex-none"
                        onClick={onStart}
                    >
                        Empezar
                        <ArrowRight aria-hidden="true" />
                    </Button>
                </div>
            </SurveyActions>
        </section>
    );
}
