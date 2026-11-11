import Pagination from '@/components/humetria/pagination';
import AssignmentGradeAdd from '@/components/leave/assignment-grade-add';
import AssignmentRow from '@/components/leave/assignment-row';
import AssignmentSelect from '@/components/leave/assignment-select';
import type {
    LeaveAssignments,
    LeaveGradeAssignment,
    LeavePolicyOption,
    Paginated,
} from '@/types';

/**
 * Whom each policy goes to: the company, each staff group and the job grades that have one of their own. The most specific
 * wins: grade, then group, then the company, then the law.
 */
export default function AssignmentTable({
    assignments,
    options,
    grades,
    canManage,
}: {
    assignments: LeaveAssignments;
    options: LeavePolicyOption[];
    grades: Paginated<LeaveGradeAssignment>;
    canManage: boolean;
}) {
    const fixed = [
        ['Toda la empresa', { scope: 'default' }, assignments.default],
        [
            'Sindicalizados',
            { scope: 'staff_group', staff_group: 'unionized' },
            assignments.unionized,
        ],
        [
            'Personal de confianza',
            { scope: 'staff_group', staff_group: 'trusted' },
            assignments.trusted,
        ],
    ] as const;

    return (
        <section
            aria-labelledby="assignments"
            className="grid gap-4 card p-[22px]"
        >
            <div>
                <h2
                    id="assignments"
                    className="text-lg leading-[26px] font-semibold"
                >
                    A quién le toca cada una
                </h2>
                <p className="text-sm text-muted-foreground">
                    Gana lo más específico: nivel de puesto, luego grupo, luego
                    toda la empresa y, al final, la ley.
                </p>
            </div>
            <ul className="divide-y">
                {fixed.map(([label, target, value]) => (
                    <AssignmentRow key={label} label={label}>
                        <AssignmentSelect
                            label={`Política de ${label.toLowerCase()}`}
                            target={target}
                            value={value}
                            options={options}
                            disabled={!canManage}
                        />
                    </AssignmentRow>
                ))}
                {grades.data.map((grade) => (
                    <AssignmentRow key={grade.id} label={grade.label}>
                        <AssignmentSelect
                            label={`Política de ${grade.label}`}
                            target={{
                                scope: 'job_grade',
                                job_grade_id: grade.id,
                            }}
                            value={grade.policyId}
                            options={options}
                            disabled={!canManage}
                        />
                    </AssignmentRow>
                ))}
            </ul>
            <Pagination
                page={grades}
                label="Páginas de niveles de puesto"
                pinned={false}
            />
            {canManage && <AssignmentGradeAdd options={options} />}
        </section>
    );
}
