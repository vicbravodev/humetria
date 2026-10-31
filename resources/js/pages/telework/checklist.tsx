import { Head, router, usePage } from '@inertiajs/react';
import { ListChecks, Send, Copy } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import CoverageWarnings from '@/components/telework/coverage-warnings';
import ChecklistSections from '@/components/telework/checklist-sections';
import { draft, publish } from '@/routes/tenant/telework/checklist';
import type { ChecklistQuestion, RequirementOption } from '@/types';

export default function ChecklistPage({
    workCenter,
    checklist,
    canEdit,
    canStartDraft,
    questions,
    requirements,
    missingLabels,
}: {
    workCenter: { id: string; name: string };
    checklist: { version: number; status: 'draft' | 'published' };
    canEdit: boolean;
    canStartDraft: boolean;
    questions: ChecklistQuestion[];
    requirements: RequirementOption[];
    missingLabels: Record<string, string[]>;
}) {
    const tenantSlug = usePage().props.tenant?.slug ?? '';
    const target = { tenant: tenantSlug, workCenter: workCenter.id };
    const isDraft = checklist.status === 'draft';
    const act = (route: ReturnType<typeof publish>) =>
        router.visit(route.url, { method: route.method, preserveScroll: true });

    return (
        <>
            <Head title={`Lista de verificación de ${workCenter.name}`} />
            <div className="flex w-full max-w-[880px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption={workCenter.name}
                    title="Lista de verificación"
                    status={
                        <Badge variant={isDraft ? 'secondary' : 'success'}>
                            Versión {checklist.version} ·{' '}
                            {isDraft ? 'borrador' : 'publicada'}
                        </Badge>
                    }
                    description={
                        isDraft
                            ? 'Ajusta las preguntas de este centro. Al publicar, la lista queda como una versión que no se puede cambiar.'
                            : 'Esta versión está publicada y no se puede cambiar. Inicia un borrador para ajustarla.'
                    }
                    facts={[
                        {
                            icon: ListChecks,
                            text: `${questions.length} preguntas`,
                        },
                    ]}
                >
                    {canEdit && (
                        <Button onClick={() => act(publish(target))}>
                            <Send aria-hidden />
                            Publicar versión {checklist.version}
                        </Button>
                    )}
                    {canStartDraft && (
                        <Button onClick={() => act(draft(target))}>
                            <Copy aria-hidden />
                            Iniciar borrador nuevo
                        </Button>
                    )}
                </PageHeader>
                {isDraft && <CoverageWarnings labels={missingLabels} />}
                <ChecklistSections
                    tenantSlug={tenantSlug}
                    workCenterId={workCenter.id}
                    questions={questions}
                    requirements={requirements}
                    canEdit={canEdit}
                />
            </div>
        </>
    );
}

ChecklistPage.layout = {
    breadcrumbs: [{ title: 'NOM-037' }, { title: 'Lista de verificación' }],
};
