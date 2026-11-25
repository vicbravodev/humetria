import { Head } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import ConnectionCard from '@/components/operations/connection-card';
import DeliveryFunnel from '@/components/operations/delivery-funnel';
import FigureCard from '@/components/operations/figure-card';
import RejectingCompanies from '@/components/operations/rejecting-companies';
import TemplateTable from '@/components/operations/template-table';
import { count, deliveredShare, trafficOf } from '@/lib/operations';
import { rejected } from '@/lib/operations-messaging';
import type { MessagingConsoleData } from '@/types';

/** «Mensajería»: WhatsApp and mail of the whole platform. States and counts only: a message's content is never shown. */
export default function Messaging({
    month,
    today,
    templates,
    companies,
    connection,
    optOuts,
}: MessagingConsoleData) {
    const whatsApp = trafficOf(month, 'whatsapp');
    const mail = trafficOf(month, 'mail');

    return (
        <>
            <Head title="Mensajería" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    caption="Plataforma"
                    title="Mensajería"
                    description="WhatsApp y correo de toda la plataforma: plantillas, entregas, rechazos y bajas. Solo estados y conteos; el contenido de un mensaje nunca se muestra."
                />
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <FigureCard
                        title="WhatsApp del mes"
                        figure={count(whatsApp?.total ?? 0)}
                        caption={`${deliveredShare(whatsApp)} entregados`}
                    />
                    <FigureCard
                        title="Correos del mes"
                        figure={count(mail?.total ?? 0)}
                        caption={`${deliveredShare(mail)} entregados`}
                    />
                    <FigureCard
                        title="Rechazos hoy"
                        figure={count(rejected(today))}
                        caption="De WhatsApp y correo, de toda la plataforma."
                    />
                    <FigureCard
                        title="Bajas de WhatsApp"
                        figure={count(
                            optOuts.everyCompany + optOuts.oneCompany,
                        )}
                        caption={`${count(optOuts.lastThirtyDays)} en los últimos 30 días`}
                    />
                </div>
                <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
                    <div className="grid min-w-0 content-start gap-4">
                        <TemplateTable templates={templates} />
                        <RejectingCompanies companies={companies} />
                    </div>
                    <div className="grid min-w-0 content-start gap-4">
                        <DeliveryFunnel
                            traffic={trafficOf(today, 'whatsapp')}
                        />
                        <ConnectionCard
                            connection={connection}
                            optOuts={optOuts}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}
