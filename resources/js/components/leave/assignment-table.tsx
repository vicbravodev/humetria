import Pagination from '@/components/humetria/pagination';
import AssignmentGradeAdd from '@/components/leave/assignment-grade-add';
import AssignmentLine from '@/components/leave/assignment-line';
import { Badge } from '@/components/ui/badge';
import { assignmentSentences, nameClash } from '@/lib/leave/compare';
import type {
    LeaveAssignments,
    LeaveGradeAssignment,
    LeavePolicyOption,
    Paginated,
} from '@/types';

const TARGETS: Record<keyof LeaveAssignments, Record<string, string>> = {
    default: { scope: 'default' },
    unionized: { scope: 'staff_group', staff_group: 'unionized' },
    trusted: { scope: 'staff_group', staff_group: 'trusted' },
};

/**
 * «A quién le toca cada una», as sentences: the company, each staff group and the job grades with a policy of their own. The
 * most specific wins: grade, then group, then the company, then the law. A group that follows the law and is named like a
 * policy gets a warning, because the policy was probably meant for it.
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
    const clash = nameClash(assignments, options);

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
                {assignmentSentences(assignments, options).map((line) => (
                    <AssignmentLine
                        key={line.key}
                        text={line.text}
                        label={line.label}
                        target={TARGETS[line.key]}
                        value={assignments[line.key]}
                        options={options}
                        canManage={canManage}
                        note={
                            clash === line.label ? (
                                <Badge variant="warning">
                                    Revisa: el grupo se llama igual que una
                                    política
                                </Badge>
                            ) : undefined
                        }
                    />
                ))}
                {grades.data.map((grade) => (
                    <AssignmentLine
                        key={grade.id}
                        text={`${grade.label} sigue ${options.find((option) => option.value === grade.policyId)?.label ?? 'la Ley (sin política)'}`}
                        label={grade.label}
                        target={{ scope: 'job_grade', job_grade_id: grade.id }}
                        value={grade.policyId}
                        options={options}
                        canManage={canManage}
                    />
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
