import { Head, setLayoutProps } from '@inertiajs/react';
import PolicyDraftActions from '@/components/disclosure/policy-draft-actions';
import PolicyHistory from '@/components/disclosure/policy-history';
import PolicyLatest from '@/components/disclosure/policy-latest';
import PolicyStart from '@/components/disclosure/policy-start';
import PageHeader from '@/components/humetria/page-header';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { disclosureCrumbs } from '@/lib/nom035/crumbs';
import type { PolicyPageProps } from '@/types';

/** The policy of prevention: the version in force and its history; it is written with questions (board V2-N035-Politica). */
export default function Policy({
    draft,
    latest,
    history,
    can,
}: PolicyPageProps) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: disclosureCrumbs(tenantSlug, {
            title: 'Política de prevención',
        }),
    });

    return (
        <>
            <Head title="Política de prevención" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="NOM-035 · numeral 5.1"
                    title="Política de prevención"
                    description="La declaración de principios y compromisos con que el centro previene los riesgos psicosociales y la violencia laboral y promueve un entorno favorable (numerales 4.9 y 5.1)."
                >
                    {can.manage && (
                        <PolicyDraftActions
                            draft={draft}
                            hasVersion={latest !== null}
                        />
                    )}
                </PageHeader>
                {latest !== null && <PolicyLatest latest={latest} />}
                {latest === null && (
                    <PolicyStart
                        canManage={can.manage}
                        drafting={draft !== null}
                    />
                )}
                {history.total > 0 && <PolicyHistory history={history} />}
            </div>
        </>
    );
}
