import { Head, setLayoutProps } from '@inertiajs/react';
import { Hourglass } from 'lucide-react';
import ResultsBody from '@/components/engagement/results/results-body';
import ResultsHeader from '@/components/engagement/results/results-header';
import EmptyState from '@/components/humetria/empty-state';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { EngagementResults } from '@/types';

export default function Results(props: EngagementResults) {
    const tenantSlug = useTenantSlug();
    const { participation, survey } = props;
    setLayoutProps({
        breadcrumbs: [
            { title: 'Clima' },
            { title: survey.name },
            { title: 'Resultados' },
        ],
    });

    return (
        <>
            <Head title={`Resultados · ${survey.name}`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <ResultsHeader
                    tenantSlug={tenantSlug}
                    survey={survey}
                    available={participation !== null}
                    cut={props.cut}
                />
                {participation === null ? (
                    <EmptyState
                        icon={Hourglass}
                        title="Los resultados estarán disponibles al cerrar la encuesta"
                    >
                        Mientras sigue abierta, cada respuesta nueva diría qué
                        contestó quien acaba de contestar. Al cerrar se calculan
                        una sola vez.
                    </EmptyState>
                ) : (
                    <ResultsBody
                        tenantSlug={tenantSlug}
                        results={props}
                        participation={participation}
                    />
                )}
            </div>
        </>
    );
}
