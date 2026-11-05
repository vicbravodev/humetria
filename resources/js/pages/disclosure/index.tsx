import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import DisclosureHeader from '@/components/disclosure/disclosure-header';
import MatrixReach from '@/components/disclosure/matrix-reach';
import MatrixTable from '@/components/disclosure/matrix-table';
import EmptyState from '@/components/humetria/empty-state';
import NextStepCard from '@/components/humetria/next-step-card';
import Pagination from '@/components/humetria/pagination';
import ProgressBar from '@/components/humetria/progress-bar';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index as campaigns } from '@/routes/tenant/campaigns';
import { index as workCenters } from '@/routes/tenant/work-centers';
import type { DisclosureIndexProps } from '@/types';

/** «Lo que tus personas deben conocer» (board V4-Difusion): the six things of 5.7 × the centers, and the one next step. */
export default function DisclosureIndex({
    centers,
    items,
    progress,
    next,
    reach,
    can,
}: DisclosureIndexProps) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'NOM-035', href: campaigns(tenantSlug) },
            { title: 'Difusión' },
        ],
    });

    return (
        <>
            <Head title="Lo que tus personas deben conocer" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <DisclosureHeader
                    eyebrow="NOM-035 · numerales 5.1, 5.7 y 7.8"
                    title="Lo que tus personas deben conocer"
                    lead={`La norma pide que cada persona pueda consultar ${progress.total} cosas. Escribes cada una una vez y llega a tus centros por su página, el cartel y el correo.`}
                >
                    <div className="flex w-full max-w-[240px] flex-col gap-2">
                        <p className="flex justify-between gap-3 text-sm">
                            <span className="font-medium">
                                {progress.ready} de {progress.total} listas
                            </span>
                            {progress.minutes > 0 && (
                                <span className="text-muted-foreground">
                                    ≈ {progress.minutes} min lo demás
                                </span>
                            )}
                        </p>
                        <ProgressBar
                            value={progress.ready}
                            max={progress.total}
                            label="Cosas listas en todos tus centros"
                        />
                    </div>
                </DisclosureHeader>
                {next !== null && (
                    <NextStepCard
                        title={next.title}
                        meta={`Numeral ${items.find(({ key }) => key === next.item)?.numeral ?? ''}`}
                        action={
                            <Button asChild>
                                <Link href={next.action.href}>
                                    {next.action.label}
                                </Link>
                            </Button>
                        }
                    >
                        {next.text}
                    </NextStepCard>
                )}
                {centers.total === 0 ? (
                    <EmptyState
                        icon={Building2}
                        title="Aún no hay centros de trabajo"
                        action={
                            <Button asChild>
                                <Link href={workCenters(tenantSlug)}>
                                    Agregar un centro
                                </Link>
                            </Button>
                        }
                    >
                        Lo que tus personas deben conocer se difunde por centro
                        de trabajo.
                    </EmptyState>
                ) : (
                    <section
                        aria-label="Por centro de trabajo"
                        className="flex flex-col gap-4"
                    >
                        <MatrixTable items={items} centers={centers.data} />
                        <Pagination
                            page={centers}
                            label="Páginas de centros"
                            previous="Centros anteriores"
                            next="Siguientes centros"
                            pinned={false}
                        />
                    </section>
                )}
                <MatrixReach reach={reach} manage={can.manage} />
            </div>
        </>
    );
}
