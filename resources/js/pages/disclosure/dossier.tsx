import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import DossierEvidence from '@/components/disclosure/dossier-evidence';
import RequirementRow from '@/components/disclosure/requirement-row';
import PageHeader from '@/components/humetria/page-header';
import PrintButton from '@/components/humetria/print-button';
import { Button } from '@/components/ui/button';
import { useLightPrint } from '@/hooks/use-light-print';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { disclosureCrumbs } from '@/lib/nom035/crumbs';
import { index } from '@/routes/tenant/disclosure';
import type { DossierProps } from '@/types';

export default function Dossier(data: DossierProps) {
    const tenantSlug = useTenantSlug();
    const { header, requirements, note } = data;
    useLightPrint();
    setLayoutProps({
        breadcrumbs: disclosureCrumbs(
            tenantSlug,
            { title: header.workCenter },
            { title: 'Expediente' },
        ),
    });

    return (
        <>
            <Head title={`Expediente de difusión · ${header.workCenter}`} />
            <div className="flex w-full flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16 print:p-0">
                <PageHeader
                    caption={`${header.company} · ${header.legalName}`}
                    title={`Expediente de difusión · ${header.workCenter}`}
                    description={`Emitido el ${header.issuedOn}. Política de prevención, difusión y resultados (numerales 5.1, 5.7 y 7.8).`}
                >
                    <div className="flex flex-wrap gap-2 print:hidden">
                        <Button asChild variant="outline">
                            <Link href={index.url(tenantSlug)}>
                                <ArrowLeft aria-hidden />
                                Volver
                            </Link>
                        </Button>
                        <PrintButton />
                    </div>
                </PageHeader>
                <section aria-label="Requisitos" className="card p-5">
                    <ul>
                        {requirements.map((requirement) => (
                            <RequirementRow
                                key={requirement.code}
                                requirement={requirement}
                            />
                        ))}
                    </ul>
                </section>
                <DossierEvidence data={data} />
                <p className="text-meta text-muted-foreground">{note}</p>
            </div>
        </>
    );
}
