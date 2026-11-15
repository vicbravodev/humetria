import CutParticipation from '@/components/engagement/cut-participation';
import ParticipationCard from '@/components/engagement/participation-card';
import { progressReading } from '@/lib/engagement/panel';
import type { ClimateParticipation } from '@/types';

/** The survey in progress (pantalla Eval-Activa): how far it is, and how each group is doing. */
export default function ActiveFollowUp({
    participation,
    daysLeft,
}: {
    participation: ClimateParticipation;
    daysLeft: number | null;
}) {
    const reading = progressReading(participation, daysLeft);

    return (
        <>
            {reading !== null && (
                <h2 className="text-xl leading-7 font-semibold text-pretty">
                    {reading}
                </h2>
            )}
            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.8fr)]">
                <ParticipationCard
                    participation={participation}
                    closed={false}
                />
                <CutParticipation
                    cuts={participation.cuts}
                    minGroup={participation.minGroup}
                    overall={participation.rate}
                />
            </div>
        </>
    );
}
