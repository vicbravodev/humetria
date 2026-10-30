import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, HeartHandshake } from 'lucide-react';
import ConsolePagination from '@/components/console-pagination';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/page-header';
import ScreeningsTable from '@/components/results/screenings-table';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { results } from '@/routes/tenant/campaigns';
import type { Paginated, ScreenedPerson, ScreeningCounts } from '@/types';

export default function Screenings({
    campaign,
    counts,
    people,
}: {
    campaign: { id: string; workCenter: string; kindLabel: string };
    counts: ScreeningCounts;
    people: Paginated<ScreenedPerson>;
}) {
    const tenantSlug = usePage().props.tenant?.slug ?? '';

    return (
        <>
            <Head title={`Guía I · ${campaign.workCenter}`} />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <Link
                    href={results([tenantSlug, campaign.id])}
                    className="flex w-fit items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    Resultados
                </Link>
                <PageHeader
                    caption={`Guía I · ${campaign.kindLabel}`}
                    title="Personas que requieren valoración clínica"
                />
                <Alert>
                    <AlertDescription>
                        {counts.requiring} de {counts.answered} personas que
                        contestaron la Guía I en {campaign.workCenter} requieren
                        valoración clínica. Canalízalas a atención (num. 5.5) y
                        conserva sus nombres en el registro (num. 5.8). Esta
                        información es confidencial: no la compartas fuera de
                        quienes las atienden.
                    </AlertDescription>
                </Alert>
                {people.total === 0 ? (
                    <EmptyState
                        icon={HeartHandshake}
                        title="Nadie requiere valoración clínica"
                    >
                        Cuando alguien conteste la Guía I y la requiera,
                        aparecerá aquí.
                    </EmptyState>
                ) : (
                    <>
                        <ScreeningsTable people={people.data} />
                        <ConsolePagination
                            page={people}
                            label="Páginas de personas"
                            previous="Anteriores"
                            next="Siguientes"
                        />
                    </>
                )}
            </div>
        </>
    );
}
