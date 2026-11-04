import { Head, setLayoutProps } from '@inertiajs/react';
import DisclosureTabs from '@/components/disclosure/disclosure-tabs';
import PolicyDraftForm from '@/components/disclosure/policy-draft-form';
import PolicyDraftActions from '@/components/disclosure/policy-draft-actions';
import PolicyHistory from '@/components/disclosure/policy-history';
import PolicyLatest from '@/components/disclosure/policy-latest';
import PolicyStart from '@/components/disclosure/policy-start';
import PageHeader from '@/components/humetria/page-header';
import type { PolicyPageProps } from '@/types';

export default function Policy({
    draft,
    latest,
    history,
    centersInUse,
    note,
    can,
}: PolicyPageProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'NOM-035' }, { title: 'Política y difusión' }],
    });

    return (
        <>
            <Head title="Política de prevención" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Política y difusión"
                    title="Política de prevención"
                    description="La declaración de principios y compromisos con que el centro previene los riesgos psicosociales y la violencia laboral y promueve un entorno favorable (numerales 4.9 y 5.1)."
                >
                    {can.manage && (
                        <PolicyDraftActions
                            draft={draft}
                            hasVersion={latest !== null}
                            centersInUse={centersInUse}
                        />
                    )}
                </PageHeader>
                <DisclosureTabs active="policy" />
                {draft !== null && (
                    <PolicyDraftForm key={draft.id} draft={draft} note={note} />
                )}
                {latest !== null && <PolicyLatest latest={latest} />}
                {draft === null && latest === null && (
                    <PolicyStart canManage={can.manage} />
                )}
                {history.total > 0 && <PolicyHistory history={history} />}
            </div>
        </>
    );
}
