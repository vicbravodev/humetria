import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ChannelSummary from '@/components/complaints/channel-summary';
import CoverageList from '@/components/complaints/coverage-list';
import EvidenceMechanism from '@/components/complaints/evidence-mechanism';
import PrintEvidenceButton from '@/components/complaints/print-evidence-button';
import PageHeader from '@/components/humetria/page-header';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { describeDate } from '@/lib/calendar-date';
import { countOf } from '@/lib/plural';
import { show } from '@/routes/tenant/complaints/channel';
import type {
    ChannelSummary as Summary,
    CoverageRow,
    EvidenceChannel,
} from '@/types';

export default function Evidence({
    company,
    channel,
    handlers,
    types,
    coverage,
    summary,
    generatedOn,
}: {
    company: string;
    channel: EvidenceChannel;
    handlers: { count: number; independent: boolean };
    types: { value: string; label: string }[];
    coverage: CoverageRow[];
    summary: Summary;
    generatedOn: string;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <>
            <Head title="Evidencia del mecanismo de quejas" />
            <div className="mx-auto flex w-full max-w-[880px] flex-col gap-6 px-4 py-6 md:px-6 md:py-8 print:max-w-none print:p-0">
                <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <Button variant="outline" asChild>
                        <Link href={show.url(tenantSlug)}>
                            <ArrowLeft aria-hidden />
                            Volver al canal
                        </Link>
                    </Button>
                    <PrintEvidenceButton />
                </div>
                <PageHeader
                    caption={`${company} · NOM-035-STPS-2018, numerales 8.1 b y 10.2`}
                    title="Mecanismo seguro y confidencial para quejas y denuncias"
                    description={`Evidencia de que la empresa dispone del mecanismo. Generada el ${describeDate(generatedOn) ?? generatedOn}. No incluye el contenido de ningún caso.`}
                />
                <EvidenceMechanism channel={channel} types={types} />
                <section className="flex flex-col gap-2">
                    <h2 className="text-lg font-semibold">
                        Procedimiento por escrito
                    </h2>
                    <p className="card p-5 whitespace-pre-line">
                        {channel.procedure}
                    </p>
                </section>
                <section className="flex flex-col gap-2">
                    <h2 className="text-lg font-semibold">Quién lo atiende</h2>
                    <p>
                        {countOf(
                            handlers.count,
                            'persona designada',
                            'personas designadas',
                        )}
                        .{' '}
                        {handlers.independent
                            ? 'Al menos una es ajena a recursos humanos y a la dirección.'
                            : 'Ninguna es ajena a recursos humanos y a la dirección.'}
                    </p>
                    <CoverageList rows={coverage} />
                </section>
                <ChannelSummary summary={summary} />
            </div>
        </>
    );
}

Evidence.layout = {
    breadcrumbs: [
        { title: 'Quejas' },
        { title: 'Canal' },
        { title: 'Evidencia' },
    ],
};
