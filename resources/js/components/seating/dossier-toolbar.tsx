import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import PrintButton from '@/components/humetria/print-button';
import { useSeatingHref } from '@/hooks/use-seating-href';

/** What the dossier does on screen and hides on paper: go back to the center and print or save as PDF. */
export default function DossierToolbar({
    workCenterId,
}: {
    workCenterId: string;
}) {
    const hrefOf = useSeatingHref(workCenterId);

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
            <Link
                href={hrefOf('positions')}
                className="flex min-h-11 w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft aria-hidden="true" className="size-[18px]" />
                Centro de trabajo
            </Link>
            <PrintButton />
        </div>
    );
}
