import { Head, setLayoutProps } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import ReadOnlyNotice from '@/components/humetria/read-only-notice';
import PolicyForm from '@/components/leave/policy-form';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/vacations/policies';
import type { LeavePolicyData } from '@/types';

/** A policy of the company, new or being changed: its terms and the days of each range of years, never under the law. */
export default function LeavePolicy({
    policy,
    floors,
    canManage = true,
}: {
    policy: LeavePolicyData | null;
    /** The days the law gives for year 1, 2, 3 and on. */
    floors: number[];
    canManage?: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const title = policy === null ? 'Nueva política' : policy.name;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Vacaciones' },
            { title: 'Políticas', href: index(tenantSlug) },
            { title },
        ],
    });

    return (
        <>
            <Head title={title} />
            <div className="mx-auto flex w-full max-w-[860px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Políticas de vacaciones"
                    title={title}
                    description="Una política solo puede mejorar la ley: ningún rango por debajo de sus días ni una prima menor a 25 %."
                />
                {!canManage && <ReadOnlyNotice />}
                <PolicyForm
                    policy={policy}
                    floors={floors}
                    canManage={canManage}
                />
            </div>
        </>
    );
}
