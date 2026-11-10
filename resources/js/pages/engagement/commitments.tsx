import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { BarChart3, Hourglass } from 'lucide-react';
import CommitmentsBoard from '@/components/engagement/commitments/commitments-board';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { results } from '@/routes/tenant/climate';
import type { EngagementCommitments } from '@/types';

export default function Commitments(props: EngagementCommitments) {
    const tenantSlug = useTenantSlug();
    const { survey, available } = props;
    setLayoutProps({
        breadcrumbs: [
            { title: 'Clima' },
            { title: survey.name },
            { title: 'Compromisos' },
        ],
    });

    return (
        <>
            <Head title={`Compromisos · ${survey.name}`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Nos dijeron / Haremos"
                    title={survey.name}
                    status={<Badge variant="secondary">Compromisos</Badge>}
                    description="Lo que la empresa hará con lo que dijo su gente: quién responde y para cuándo. Un grupo tiene a lo más 3."
                >
                    {available && (
                        <Button asChild variant="outline">
                            <Link href={results([tenantSlug, survey.id])}>
                                <BarChart3 aria-hidden />
                                Ver resultados
                            </Link>
                        </Button>
                    )}
                </PageHeader>
                {available ? (
                    <CommitmentsBoard {...props} />
                ) : (
                    <EmptyState
                        icon={Hourglass}
                        title="Los compromisos se registran al cerrar la encuesta"
                    >
                        Antes de que cierre no hay resultados que atender, y
                        cada grupo se muestra solo cuando ya es lo bastante
                        grande para no señalar a nadie.
                    </EmptyState>
                )}
            </div>
        </>
    );
}
