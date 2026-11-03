import { Head, setLayoutProps } from '@inertiajs/react';
import PolicyDraftForm from '@/components/disclosure/policy-draft-form';
import PolicyStart from '@/components/disclosure/policy-start';
import PageHeader from '@/components/humetria/page-header';
import type { PolicyPageProps } from '@/types';

export default function Policy({ draft, note, can }: PolicyPageProps) {
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
                />
                {draft === null ? (
                    <PolicyStart canManage={can.manage} />
                ) : (
                    <PolicyDraftForm key={draft.id} draft={draft} note={note} />
                )}
            </div>
        </>
    );
}
