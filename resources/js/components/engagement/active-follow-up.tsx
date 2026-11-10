import CutParticipation from '@/components/engagement/cut-participation';
import ParticipationCard from '@/components/engagement/participation-card';
import type { ClimateParticipation } from '@/types';

/** The survey in progress (pantalla Eval-Activa): how far it is, and how each group is doing. */
export default function ActiveFollowUp({
    participation,
}: {
    participation: ClimateParticipation;
}) {
    return (
        <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
            <ParticipationCard participation={participation} closed={false} />
            <CutParticipation
                cuts={participation.cuts}
                minGroup={participation.minGroup}
            />
        </div>
    );
}
