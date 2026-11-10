import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import ReadOnlyNotice from '@/components/humetria/read-only-notice';
import LawCard from '@/components/leave/law-card';
import PolicyCard from '@/components/leave/policy-card';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { create } from '@/routes/tenant/vacations/policies';
import type { LeaveLaw, LeavePolicyCard, Paginated } from '@/types';

/**
 * The policies of the company: the law first, which cannot be edited, then each policy of its own, which can only improve
 * it. Days and percentages, never an amount.
 */
export default function LeavePolicies({
    law,
    policies,
    canManage,
}: {
    law: LeaveLaw;
    policies: Paginated<LeavePolicyCard>;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();

    setLayoutProps({
        breadcrumbs: [{ title: 'Vacaciones' }, { title: 'Políticas' }],
    });

    return (
        <>
            <Head title="Políticas de vacaciones" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Vacaciones"
                    title="Políticas"
                    description="Cuántos días da la empresa y con qué prima. Una política solo puede mejorar la ley, nunca quedar por debajo."
                >
                    {canManage && (
                        <Button asChild>
                            <Link href={create(tenantSlug)}>
                                <Plus />
                                Nueva política
                            </Link>
                        </Button>
                    )}
                </PageHeader>
                {!canManage && <ReadOnlyNotice />}
                <section
                    aria-label="Políticas"
                    className="grid grid-cols-[repeat(auto-fill,minmax(min(360px,100%),1fr))] gap-5"
                >
                    <LawCard law={law} />
                    {policies.data.map((policy) => (
                        <PolicyCard
                            key={policy.id}
                            policy={policy}
                            canManage={canManage}
                        />
                    ))}
                </section>
                <Pagination page={policies} label="Páginas de políticas" />
            </div>
        </>
    );
}
