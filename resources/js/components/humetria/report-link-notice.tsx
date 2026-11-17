import { Link2 } from 'lucide-react';
import type { ReactNode } from 'react';

/** Who shared the report and until when its link works, in one line beside its icon (board ReporteCompartido). Not printed. */
export default function ReportLinkNotice({
    children,
}: {
    children: ReactNode;
}) {
    return (
        <p
            data-slot="report-link-notice"
            className="flex items-center gap-2.5 rounded-xl bg-accent px-3.5 py-2.5 text-meta text-accent-foreground print:hidden"
        >
            <Link2 aria-hidden className="size-5 shrink-0 text-primary" />
            <span>{children}</span>
        </p>
    );
}
