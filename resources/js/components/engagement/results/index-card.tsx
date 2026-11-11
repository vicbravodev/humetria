import ArcMeter from '@/components/humetria/arc-meter';
import TrendBadge from '@/components/engagement/results/trend-badge';
import { favorableText } from '@/lib/engagement/format';
import type { Delta } from '@/lib/engagement/format';
import type { EngagementParticipation } from '@/types';

/**
 * The survey at a glance (pantallas: Eval-Cerrada): the engagement index in one sentence, and how many answered in the
 * arch of the logo.
 */
export default function IndexCard({
    index,
    delta,
    stay,
    participation,
    previousName,
}: {
    /** Null when one of the three questions behind it is hidden. */
    index: number | null;
    delta: Delta | null;
    stay: number | null;
    participation: EngagementParticipation;
    previousName: string | null;
}) {
    const answered = `${participation.responses} de ${participation.invited} contestaron`;

    return (
        <section
            aria-label="Resumen"
            className="grid gap-x-12 gap-y-7 card p-5 sm:p-7 lg:grid-cols-2"
        >
            <div className="flex flex-col gap-4">
                <p className="text-sm text-muted-foreground">
                    Índice de compromiso
                </p>
                {index === null ? (
                    <p className="max-w-[520px] text-[17px] leading-[26px] text-muted-foreground">
                        Sin respuestas suficientes para mostrar el índice: se
                        necesitan al menos 5 en cada una de sus tres preguntas.
                    </p>
                ) : (
                    <p className="max-w-[520px] text-[22px] leading-8 font-medium tracking-[-0.01em] text-pretty">
                        {favorableText(index)} de las respuestas al compromiso
                        son favorables.
                    </p>
                )}
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <TrendBadge delta={delta} />
                    {previousName !== null && delta !== null && (
                        <span className="text-caption text-muted-foreground">
                            frente a {previousName}
                        </span>
                    )}
                </div>
                <p className="text-sm text-muted-foreground">
                    Quieren seguir un año más:{' '}
                    <strong className="font-medium text-foreground">
                        {favorableText(stay)}
                    </strong>
                </p>
            </div>
            <div className="flex items-center justify-center border-t pt-7 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-7">
                <ArcMeter
                    value={participation.responses}
                    max={participation.invited}
                    label={answered}
                    size="lg"
                >
                    <span className="text-[40px] leading-none font-semibold tracking-[-0.03em] tabular-nums">
                        {favorableText(participation.percent)}
                    </span>
                    <span className="max-w-[100px] text-caption leading-4 text-muted-foreground">
                        {answered}
                    </span>
                </ArcMeter>
            </div>
        </section>
    );
}
