import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Plus, Scale } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import ReadOnlyNotice from '@/components/humetria/read-only-notice';
import ArchivedPolicies from '@/components/leave/archived-policies';
import AssignmentTable from '@/components/leave/assignment-table';
import PolicyComparison from '@/components/leave/policy-comparison';
import PolicyFirst from '@/components/leave/policy-first';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { leaveTrail } from '@/lib/leave/trail';
import { create } from '@/routes/tenant/vacations/policies';
import type {
    LeaveAssignments,
    LeaveGradeAssignment,
    LeaveLaw,
    LeavePolicyCard,
    LeavePolicyOption,
    Paginated,
} from '@/types';

/**
 * The policies of the company against the law: the law first (it cannot be edited), then each active policy on the same
 * rows and scale, who follows each, and the archived ones folded in a line. Days and percentages, never an amount.
 */
export default function LeavePolicies({
    law,
    lawByYear,
    lawPeople,
    policies,
    assignments,
    policyOptions,
    grades,
    canManage,
}: {
    law: LeaveLaw;
    lawByYear: number[];
    lawPeople: number;
    policies: Paginated<LeavePolicyCard>;
    assignments: LeaveAssignments;
    /** The active policies, to choose which one a target follows. */
    policyOptions: LeavePolicyOption[];
    grades: Paginated<LeaveGradeAssignment>;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const active = policies.data.filter((policy) => !policy.archived);
    const archived = policies.data.filter((policy) => policy.archived);

    setLayoutProps({ breadcrumbs: leaveTrail('Políticas') });

    return (
        <>
            <Head title="Políticas de vacaciones" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Políticas"
                    facts={[
                        {
                            icon: Scale,
                            text: 'Una política solo puede mejorar la ley',
                        },
                    ]}
                >
                    {canManage && (
                        <Button asChild className="max-sm:h-11 max-sm:w-full">
                            <Link href={create(tenantSlug)}>
                                <Plus />
                                Nueva política
                            </Link>
                        </Button>
                    )}
                </PageHeader>
                {!canManage && <ReadOnlyNotice />}
                <PolicyComparison
                    law={law}
                    lawByYear={lawByYear}
                    lawPeople={lawPeople}
                    policies={active}
                    assignments={assignments}
                    canManage={canManage}
                />
                {active.length === 0 && <PolicyFirst canManage={canManage} />}
                <Pagination
                    page={policies}
                    label="Páginas de políticas"
                    pinned={false}
                />
                <ArchivedPolicies policies={archived} />
                {active.length > 0 && (
                    <AssignmentTable
                        assignments={assignments}
                        options={policyOptions}
                        grades={grades}
                        canManage={canManage}
                    />
                )}
            </div>
        </>
    );
}
