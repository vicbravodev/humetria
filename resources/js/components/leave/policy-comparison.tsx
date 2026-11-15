import PolicyActions from '@/components/leave/policy-actions';
import PolicyColumn from '@/components/leave/policy-column';
import type { PolicyColumnData } from '@/components/leave/policy-column';
import { appliesTo, daysDelta, premiumDelta } from '@/lib/leave/compare';
import { fixedDaysLine } from '@/lib/leave/policy';
import { niceCeiling } from '@/lib/chart';
import { countOf } from '@/lib/plural';
import type { LeaveAssignments, LeaveLaw, LeavePolicyCard } from '@/types';

/** «Sindicalizados · 78 personas», or only the people when no group names it. */
function appliesText(groups: string[], people: number): string {
    const count = countOf(people, 'persona', 'personas');

    return groups.length === 0 ? count : `${groups.join(' y ')} · ${count}`;
}

/**
 * The law and the policies of the company side by side, on the same rows and the same scale, so two can be compared without
 * opening either: the law first (it cannot be edited), then each active policy with its menu.
 */
export default function PolicyComparison({
    law,
    lawByYear,
    lawPeople,
    policies,
    assignments,
    canManage,
}: {
    law: LeaveLaw;
    lawByYear: number[];
    lawPeople: number;
    policies: LeavePolicyCard[];
    assignments: LeaveAssignments;
    canManage: boolean;
}) {
    const top = niceCeiling(
        Math.max(...lawByYear, ...policies.flatMap((policy) => policy.byYear)),
        5,
    );
    const lawColumn: PolicyColumnData = {
        name: 'Ley',
        firstYearDays: law.firstYearDays,
        premiumPercent: law.premiumPercent,
        fixed: null,
        byYear: lawByYear,
        applies: `Quien no tiene política · ${countOf(lawPeople, 'persona', 'personas')}`,
        daysDelta: null,
        premiumDelta: null,
    };

    return (
        <section
            aria-label="Comparador de políticas"
            className="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <PolicyColumn
                column={lawColumn}
                lawByYear={lawByYear}
                top={top}
                badge="No se puede editar"
            />
            {policies.map((policy) => (
                <PolicyColumn
                    key={policy.id}
                    column={{
                        name: policy.name,
                        firstYearDays: policy.firstYearDays,
                        premiumPercent: policy.premiumPercent,
                        fixed:
                            policy.fixedDays > 0
                                ? fixedDaysLine(policy.fixedDays)
                                : null,
                        byYear: policy.byYear,
                        applies: appliesText(
                            appliesTo(policy.id, assignments),
                            policy.people,
                        ),
                        daysDelta: daysDelta(
                            policy.firstYearDays,
                            law.firstYearDays,
                        ),
                        premiumDelta: premiumDelta(
                            policy.premiumPercent,
                            law.premiumPercent,
                        ),
                    }}
                    lawByYear={lawByYear}
                    top={top}
                    actions={
                        <PolicyActions policy={policy} canManage={canManage} />
                    }
                />
            ))}
        </section>
    );
}
