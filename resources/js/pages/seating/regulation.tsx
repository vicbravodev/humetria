import { Head, setLayoutProps } from '@inertiajs/react';
import { Factory, Printer } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import RegulationBody from '@/components/seating/regulation-body';
import RegulationEmpty from '@/components/seating/regulation-empty';
import RegulationNotice from '@/components/seating/regulation-notice';
import { Button } from '@/components/ui/button';
import { useLightPrint } from '@/hooks/use-light-print';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { center, index } from '@/routes/tenant/seating';
import type { RegulationRecord } from '@/types';

export default function SeatingRegulation({
    workCenter,
    regulation,
    notice,
    canManage,
}: {
    workCenter: { id: string; name: string };
    regulation: RegulationRecord | null;
    notice: string;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    useLightPrint();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Ley Silla', href: index(tenantSlug) },
            {
                title: workCenter.name,
                href: center([tenantSlug, workCenter.id]),
            },
            { title: 'Reglamento interior' },
        ],
    });

    return (
        <>
            <Head title={`Reglamento interior · ${workCenter.name}`} />
            <div className="flex w-full max-w-[880px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Reglamento interior"
                    description="Lo que dice sobre trabajar de pie: factores de riesgo, pausas y dónde están los asientos."
                    facts={[{ icon: Factory, text: workCenter.name }]}
                >
                    {regulation !== null && (
                        <Button
                            variant="outline"
                            className="print:hidden"
                            onClick={() => window.print()}
                        >
                            <Printer aria-hidden />
                            Imprimir o guardar PDF
                        </Button>
                    )}
                </PageHeader>
                <RegulationNotice text={notice} />
                {regulation === null ? (
                    <RegulationEmpty
                        workCenterId={workCenter.id}
                        canManage={canManage}
                    />
                ) : (
                    <RegulationBody
                        workCenterId={workCenter.id}
                        regulation={regulation}
                        canManage={canManage}
                    />
                )}
            </div>
        </>
    );
}
