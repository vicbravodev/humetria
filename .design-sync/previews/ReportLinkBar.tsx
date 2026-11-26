import { Logo, PrintButton, ReportLinkBar, ReportLinkNotice } from 'humetria';

export const SharedReport = () => (
    <div className="bg-background">
        <ReportLinkBar start={<Logo className="h-[26px]" />} className="max-w-[1120px]">
            <PrintButton variant="outline" label="Imprimir" />
        </ReportLinkBar>
        <div className="mx-auto max-w-[1120px] px-4 pt-4">
            <ReportLinkNotice>
                Grupo Industrial del Norte compartió este informe contigo. Vence el 9 de diciembre de 2026.
            </ReportLinkNotice>
        </div>
    </div>
);

export const WithPdfLabel = () => (
    <ReportLinkBar start={<Logo kind="symbol" className="h-10" />}>
        <PrintButton />
    </ReportLinkBar>
);
