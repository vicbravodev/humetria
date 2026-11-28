import { Head } from '@inertiajs/react';
import CountTabs from '@/components/humetria/count-tabs';
import PageHeader from '@/components/humetria/page-header';
import DeliveryFunnel from '@/components/operations/delivery-funnel';
import MessagingSummary from '@/components/operations/messaging-summary';
import RejectingCompanies from '@/components/operations/rejecting-companies';
import TemplateTable from '@/components/operations/template-table';
import { trafficOf } from '@/lib/operations';
import { messaging } from '@/routes/operations';
import type { MessagingConsoleData, MessagingView } from '@/types';

/** «Mensajería» (tablero Mensajería): WhatsApp and mail of the whole platform. States and counts only, never a message. */
export default function Messaging(
    props: MessagingConsoleData & { view: MessagingView },
) {
    const { view, month, today, templates, companies } = props;
    const tab = (label: string, of: MessagingView, count?: number) => ({
        label,
        count,
        href: messaging(of === 'summary' ? {} : { query: { view: of } }),
        active: view === of,
    });

    return (
        <>
            <Head title="Mensajería" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Plataforma"
                    title="Mensajería"
                    description="WhatsApp y correo de toda la plataforma: plantillas, entregas, rechazos y bajas. Solo estados y conteos; el contenido de un mensaje nunca se muestra."
                />
                <CountTabs
                    label="Vistas de la mensajería"
                    tabs={[
                        tab('Resumen', 'summary'),
                        tab(
                            'Plantillas de WhatsApp',
                            'templates',
                            templates.length,
                        ),
                        tab(
                            'Correo',
                            'mail',
                            trafficOf(month, 'mail')?.total ?? 0,
                        ),
                        tab('Por empresa', 'companies', companies.length),
                    ]}
                />
                {view === 'summary' && <MessagingSummary {...props} />}
                {view === 'templates' && (
                    <TemplateTable templates={templates} />
                )}
                {view === 'mail' && (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <DeliveryFunnel
                            title="Correo de hoy, del envío a la lectura"
                            traffic={trafficOf(today, 'mail')}
                        />
                        <DeliveryFunnel
                            title="Correo del mes, del envío a la lectura"
                            traffic={trafficOf(month, 'mail')}
                        />
                    </div>
                )}
                {view === 'companies' && (
                    <RejectingCompanies companies={companies} />
                )}
            </div>
        </>
    );
}
