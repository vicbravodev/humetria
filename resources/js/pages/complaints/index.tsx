import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import BeforeCard from '@/components/complaints/before-card';
import CasesTabs from '@/components/complaints/cases-tabs';
import CasesToolbar from '@/components/complaints/cases-toolbar';
import ComplaintsHeader from '@/components/complaints/complaints-header';
import ConfidentialBanner from '@/components/complaints/confidential-banner';
import InboxGroups from '@/components/complaints/inbox-groups';
import Pagination from '@/components/humetria/pagination';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { firstToAcknowledge } from '@/lib/complaints/inbox';
import { show } from '@/routes/tenant/complaints/cases';
import { show as channel } from '@/routes/tenant/complaints/channel';
import type { Option } from '@/lib/option-search';
import type { CaseCounts, CaseFilters, CaseRow, Paginated } from '@/types';

type Props = {
    cases: Paginated<CaseRow>;
    status: CaseFilters['status'];
    counts: CaseCounts;
    filters: Omit<CaseFilters, 'status'>;
    types: Option[];
    today: string;
    acknowledgeWithinDays: number;
};

/** «Casos por atender» (board V4-Quejas-Bandeja): the cases assigned to you, grouped by what is due, with its deadline. */
export default function Cases(props: Props) {
    const { cases, status, counts, filters, types } = props;
    const tenantSlug = useTenantSlug();
    const manages =
        usePage().props.tenant?.can.manage_complaint_channel ?? false;
    const before =
        status === 'closed'
            ? null
            : firstToAcknowledge(
                  cases.data,
                  props.today,
                  props.acknowledgeWithinDays,
              );
    setLayoutProps({ breadcrumbs: [{ title: 'Quejas' }] });

    return (
        <>
            <Head title="Quejas y denuncias" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <ConfidentialBanner>
                    Solo ves los casos que te asignaron. Cada vez que abres uno
                    queda registrado en la bitácora del caso.
                </ConfidentialBanner>
                <ComplaintsHeader
                    eyebrow="NOM-035 · numeral 8.1 b"
                    title="Quejas y denuncias"
                    lead="Atiéndelas en orden: primero confirma que las recibiste, luego revisa y responde."
                >
                    <CasesTabs
                        filters={{ ...filters, status }}
                        counts={counts}
                    />
                    {manages && (
                        <Button variant="outline" asChild>
                            <Link href={channel(tenantSlug)}>Canal</Link>
                        </Button>
                    )}
                </ComplaintsHeader>
                {before !== null && (
                    <BeforeCard
                        eyebrow="Antes de seguir"
                        title={before.title}
                        action={
                            <Button asChild>
                                <Link
                                    href={show({
                                        tenant: tenantSlug,
                                        complaint: before.id,
                                    })}
                                >
                                    Acusar recibo
                                    <ArrowRight aria-hidden />
                                </Link>
                            </Button>
                        }
                    >
                        {before.text}
                    </BeforeCard>
                )}
                <CasesToolbar filters={{ ...filters, status }} types={types} />
                <InboxGroups
                    cases={cases}
                    closed={status === 'closed'}
                    counts={counts}
                    filtered={
                        filters.q !== '' ||
                        filters.type !== null ||
                        filters.center !== null
                    }
                    today={props.today}
                    manages={manages}
                />
                <Pagination
                    page={cases}
                    label="Páginas de casos"
                    pinned={false}
                />
                <p className="text-caption text-muted-foreground">
                    Cuando tienes casos nuevos o una persona te escribe, te
                    avisamos por correo cuántos son, a lo más una vez por hora.
                    El correo nunca incluye el contenido.
                </p>
            </div>
        </>
    );
}
