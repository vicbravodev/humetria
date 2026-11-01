import { Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { center } from '@/routes/tenant/telework';

/** What the dossier does on screen and hides on paper: go back to the center and print or save as PDF. */
export default function DossierToolbar({
    tenantSlug,
    workCenterId,
}: {
    tenantSlug: string;
    workCenterId: string;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
            <Link
                href={center({ tenant: tenantSlug, workCenter: workCenterId })}
                className="flex min-h-11 w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft aria-hidden="true" className="size-[18px]" />
                Centro de trabajo
            </Link>
            <Button type="button" onClick={() => window.print()}>
                <Printer aria-hidden="true" />
                Imprimir o guardar PDF
            </Button>
        </div>
    );
}
