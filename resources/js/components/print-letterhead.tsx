import { usePage } from '@inertiajs/react';
import Logo from '@/components/humetria/logo';

/**
 * The letterhead of a document that goes to paper (a dossier): the Humetría logo, the company and the center, and the
 * date it was issued. It shows only when printing; the sheet's page number comes from the `dossier` named page (app.css).
 */
export default function PrintLetterhead({
    center,
    issued,
}: {
    center: string;
    issued: string;
}) {
    const { tenant } = usePage().props;

    return (
        <div
            data-slot="print-letterhead"
            className="hidden items-center justify-between gap-6 border-b pb-3 print:flex"
        >
            <Logo className="h-7" />
            <div className="flex flex-col text-right text-meta">
                <p className="font-semibold">
                    {tenant === null
                        ? center
                        : `${tenant.trade_name} · ${center}`}
                </p>
                <p className="text-muted-foreground">Emitido el {issued}</p>
            </div>
        </div>
    );
}
