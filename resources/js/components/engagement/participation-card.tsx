import ArcMeter from '@/components/humetria/arc-meter';
import { pendingText, withoutLinkText } from '@/lib/engagement/panel';
import type { ClimateParticipation } from '@/types';

/**
 * How far the survey has come (pantalla Clima · Avance): the share inside the arch of the logo with the count below it,
 * and how many are missing. Never what anyone answered, nor who has not.
 */
export default function ParticipationCard({
    participation,
    closed,
}: {
    participation: ClimateParticipation;
    closed: boolean;
}) {
    const counted = `${participation.answered} de ${participation.invited} contestaron`;
    const withoutLink = withoutLinkText(participation.withoutLink);

    return (
        <article className="flex flex-col items-center gap-4 card p-6 text-center">
            <ArcMeter
                value={participation.answered}
                max={participation.invited}
                label={counted}
                showPercent
            />
            <p className="-mt-2 text-sm text-muted-foreground">{counted}</p>
            {!closed && (
                <p className="text-lg font-medium">
                    {pendingText(participation.pending)}
                </p>
            )}
            {withoutLink !== undefined && (
                <p className="text-sm text-muted-foreground">{withoutLink}</p>
            )}
        </article>
    );
}
