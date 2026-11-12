/** What only the printed report carries: the day it was issued and a line for the signature of whoever answers for it. */
export default function ReportSignature({
    issuedOn,
    responsible,
}: {
    issuedOn: string;
    responsible: string | null;
}) {
    return (
        <div
            data-slot="report-signature"
            className="hidden break-inside-avoid flex-col gap-12 pt-6 print:flex"
        >
            <p>Fecha de emisión: {issuedOn}</p>
            <div className="w-72 border-t border-foreground pt-1 text-sm">
                {responsible ?? 'Responsable de la evaluación'}
                <span className="block text-muted-foreground">Firma</span>
            </div>
        </div>
    );
}
