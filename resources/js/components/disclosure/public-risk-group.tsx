import LevelBar from '@/components/humetria/level-bar';
import type { PublicResults, PublicRiskGroup } from '@/types';

type Available = Extract<
    NonNullable<PublicResults['published']>,
    { state: 'available' }
>;

/** A category or a domain of the published results: its name and how many questionnaires fall in each level. */
export default function PublicRiskGroupRow({
    group,
    levels,
}: {
    group: PublicRiskGroup;
    levels: Available['levels'];
}) {
    return (
        <li className="flex flex-col gap-1.5">
            <span className="text-base font-medium">{group.label}</span>
            <LevelBar
                counts={group.levels}
                levels={levels.map((level) => ({ ...level, action: '' }))}
                label={group.label}
            />
        </li>
    );
}
