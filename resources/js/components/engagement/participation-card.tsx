import ArcMeter from '@/components/humetria/arc-meter';
import { pendingText, withoutLinkText } from '@/lib/engagement/panel';
import type { ClimateParticipation } from '@/types';

/**
 * How far the survey has come (pantalla Eval-Activa): how many answered of how many were invited, on the arc, and how many
 * are missing. Never what anyone answered, nor who has not.
 */
export default function ParticipationCard({
    participation,
    closed,
}: {
    participation: ClimateParticipation;
    closed: boolean;
}) {
    const counted = `${participation.answered} de ${participation.invited}`;
    const withoutLink = withoutLinkText(participation.withoutLink);

    return (
        <article className="flex flex-col items-center gap-[18px] card p-6">
            <ArcMeter
                value={participation.answered}
                max={participation.invited}
                label={`${counted} contestaron`}
                size="lg"
            >
                <span className="text-[46px] leading-none font-semibold tracking-[-0.03em] tabular-nums">
                    {participation.answered}
                </span>
                <span className="max-w-[116px] text-caption leading-4 text-muted-foreground">
                    de {participation.invited} contestaron
                </span>
            </ArcMeter>
            <div className="flex w-full flex-col gap-1.5 border-t pt-4 text-sm text-muted-foreground">
                {participation.rate !== null && (
                    <p>
                        <strong className="font-semibold text-foreground tabular-nums">
                            {participation.rate} %
                        </strong>{' '}
                        de participación.
                    </p>
                )}
                {!closed && <p>{pendingText(participation.pending)}</p>}
                {withoutLink !== undefined && <p>{withoutLink}</p>}
            </div>
        </article>
    );
}
