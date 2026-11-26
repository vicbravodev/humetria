import ConnectionCard from '@/components/operations/connection-card';
import DeliveryFunnel from '@/components/operations/delivery-funnel';
import FigureCard from '@/components/operations/figure-card';
import RejectingCompanies from '@/components/operations/rejecting-companies';
import TemplateTable from '@/components/operations/template-table';
import { count, deliveredShare, trafficOf } from '@/lib/operations';
import { rejected } from '@/lib/operations-messaging';
import type { MessagingConsoleData } from '@/types';

/** «Resumen» (tablero Mensajería): four figures, the templates and the companies, and beside them the day and Meta. */
export default function MessagingSummary({
    month,
    today,
    templates,
    companies,
    connection,
    optOuts,
}: MessagingConsoleData) {
    const whatsApp = trafficOf(month, 'whatsapp');

    return (
        <>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <FigureCard
                    title="WhatsApp del mes"
                    figure={count(whatsApp?.total ?? 0)}
                    caption="mensajes de toda la plataforma"
                />
                <FigureCard
                    title="Entregados"
                    figure={deliveredShare(whatsApp)}
                    caption="del WhatsApp del mes"
                />
                <FigureCard
                    title="Rechazos hoy"
                    figure={count(rejected(today))}
                    caption="de WhatsApp y correo"
                />
                <FigureCard
                    title="Bajas de WhatsApp"
                    figure={count(optOuts.everyCompany + optOuts.oneCompany)}
                    caption={`${count(optOuts.lastThirtyDays)} en los últimos 30 días`}
                />
            </div>
            <div className="flex flex-wrap items-start gap-6">
                <div className="flex min-w-0 flex-[999_1_560px] flex-col gap-6">
                    <TemplateTable templates={templates} />
                    <RejectingCompanies companies={companies} />
                </div>
                <div className="flex max-w-full min-w-0 flex-[1_1_320px] flex-col gap-6">
                    <DeliveryFunnel
                        title="Del envío a la lectura · hoy"
                        traffic={trafficOf(today, 'whatsapp')}
                    />
                    <ConnectionCard connection={connection} optOuts={optOuts} />
                </div>
            </div>
        </>
    );
}
